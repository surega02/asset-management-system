<?php

namespace App\Actions\Imports;

use App\Jobs\ProcessAssetImport;
use App\Models\Asset;
use App\Models\ImportRow;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssetImportBatches
{
    public function __construct(private AssetImportSheet $sheet, private ImportAssetMutation $mutation) {}

    public static function recent(): Builder
    {
        return DB::table('import_batches')->where('created_at', '>', now()->subDays(90));
    }

    public function owned(User $actor, int $id, bool $lock = false): object
    {
        Gate::forUser($actor)->authorize('import', Asset::class);
        $batch = self::recent()->where('user_id', $actor->id)->when($lock, fn ($query) => $query->lockForUpdate())->find($id);
        abort_if($batch === null, 404);

        return $batch;
    }

    public function upload(User $actor, UploadedFile $file): int
    {
        Gate::forUser($actor)->authorize('import', Asset::class);
        $rows = $this->sheet->read($file);

        return DB::transaction(function () use ($actor, $rows): int {
            $actor = IdentityAccess::lockScopedActor($actor);
            Gate::forUser($actor)->authorize('import', Asset::class);
            $id = DB::table('import_batches')->insertGetId([
                'user_id' => $actor->id, 'status' => 'preview', 'total_rows' => count($rows), 'created_at' => now(),
            ]);
            foreach (array_chunk($rows, 25, preserve_keys: true) as $chunk) {
                $inserts = [];
                foreach ($chunk as $line => $payload) {
                    $inserts[] = [
                        'import_batch_id' => $id, 'row_number' => $line, 'action' => $this->mutation->candidate($actor, $payload),
                        'status' => 'preview', 'payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
                    ];
                }
                DB::table('import_rows')->insert($inserts);
            }

            return $id;
        }, 3);
    }

    public function describe(User $actor, ImportRow $row): array
    {
        $candidate = $this->mutation->candidate($actor, $row->payload);
        $messages = [];
        if ($candidate === 'skip') {
            $messages[] = __($row->payload === null ? 'asset_import.invalid_row' : 'asset_import.unavailable');
        } else {
            try {
                $this->mutation->preview($actor, $row->payload, $candidate);
            } catch (ValidationException $exception) {
                $messages = array_merge(...array_values($exception->errors()));
            } catch (AuthorizationException) {
                $messages[] = __('asset_import.unavailable');
            }
        }

        return ['candidate' => $candidate, 'messages' => $messages, 'similar' => $this->similar($actor, $row)];
    }

    public function decide(User $actor, int $id, array $choices, bool $confirm): void
    {
        DB::transaction(function () use ($actor, $id, $choices, $confirm): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            abort_unless($this->owned($actor, $id, lock: true)->status === 'preview', 409);
            $rows = ImportRow::query()->where('import_batch_id', $id)->whereIn('id', array_keys($choices))->get();
            abort_unless($rows->count() === count($choices), 404);
            foreach ($rows as $row) {
                $action = $choices[$row->id];
                if ($action !== 'skip' && $this->mutation->candidate($actor, $row->payload) !== $action) {
                    throw ValidationException::withMessages(['choices' => __('asset_import.unavailable')]);
                }
                $row->update(['action' => $action]);
            }
            if ($confirm) {
                ImportRow::query()->where('import_batch_id', $id)->update(['status' => 'pending']);
                DB::table('import_batches')->where('id', $id)->update(['status' => 'running', 'confirmed_at' => now()]);
                self::dispatch($id);
            }
        }, 3);
    }

    public function retry(User $actor, int $id, int $rowId, int $attempt, array $values): void
    {
        DB::transaction(function () use ($actor, $id, $rowId, $attempt, $values): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            abort_unless($this->owned($actor, $id, lock: true)->status === 'finished', 409);
            $row = ImportRow::query()->where('import_batch_id', $id)->lockForUpdate()->findOrFail($rowId);
            abort_unless($row->status === 'failed' && $row->attempt_count === $attempt, 409);
            $payload = AssetImportSheet::correction($row->payload, $values);
            abort_unless($this->mutation->candidate($actor, $payload) === $row->action, 403);
            $row->update(['status' => 'pending', 'payload' => $payload, 'errors' => null, 'recoveries' => 0, 'processed_at' => null]);
            DB::table('import_batches')->where('id', $id)->update(['status' => 'running', 'finished_at' => null]);
            self::dispatch($id);
        }, 3);
    }

    public static function dispatch(int $id): void
    {
        // A persisted wake-up time throttles the scheduler if enqueue or the worker is interrupted.
        DB::table('import_batches')->where('id', $id)->where('status', 'running')
            ->update(['next_dispatch_at' => now()->addMinutes(2)]);
        ProcessAssetImport::dispatch($id)->onConnection('database')->afterCommit();
    }

    public function resumeDue(): void
    {
        $due = self::recent()->where('status', 'running')->where(fn (Builder $query) => $query
            ->whereNull('next_dispatch_at')->orWhere('next_dispatch_at', '<=', now()));
        foreach ((clone $due)->orderBy('id')->limit(100)->pluck('id') as $id) {
            DB::transaction(function () use ($due, $id): void {
                if ((clone $due)->lockForUpdate()->find($id)) {
                    self::dispatch($id);
                }
            }, 3);
        }
    }

    public function similar(User $actor, ImportRow $row): bool
    {
        $payload = $row->payload ?? [];
        $code = $payload['asset_code'] ?? '';
        $otherRows = ImportRow::query()->where('import_batch_id', $row->import_batch_id)->whereKeyNot($row->id);
        if ($code !== '') {
            return $otherRows->whereRaw("UPPER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.asset_code'))) = ?", [strtoupper($code)])->exists();
        }
        $name = $payload['name'] ?? '';
        $serial = $payload['serial_number'] ?? '';
        if ($name === '' && $serial === '') {
            return false;
        }

        return $otherRows->where(fn ($query) => $query->when($name !== '', fn ($filter) => $filter->where('payload->name', $name))
            ->when($serial !== '', fn ($filter) => $filter->orWhere('payload->serial_number', $serial)))->exists()
            || Assets::operational($actor)->where(fn ($query) => $query->when($name !== '', fn ($filter) => $filter->where('name', $name))
                ->when($serial !== '', fn ($filter) => $filter->orWhere('serial_number', $serial)))->exists();
    }
}
