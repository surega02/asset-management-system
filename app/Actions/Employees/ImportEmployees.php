<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Support\IdentityAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ImportEmployees
{
    public function __construct(private ReadEmployeeSpreadsheet $reader, private ManageEmployee $employees) {}

    public function upload(User $actor, UploadedFile $file): int
    {
        Gate::forUser($actor)->authorize('import', Employee::class);
        $rows = $this->reader->read($file);
        $hash = hash_file('sha256', $file->getPathname());
        $batchId = DB::transaction(function () use ($actor, $rows, $hash): int {
            $actor = IdentityAccess::lockScopedActor($actor);
            Gate::forUser($actor)->authorize('import', Employee::class);
            $existing = DB::table('employee_imports')->where(['user_id' => $actor->id, 'fingerprint' => $hash])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->created_at >= now()->subDays(90)->toDateTimeString()) {
                    return $existing->id;
                }
                DB::table('employee_imports')->where('id', $existing->id)->delete();
            }
            $id = DB::table('employee_imports')->insertGetId(['user_id' => $actor->id, 'fingerprint' => $hash, 'created_at' => now()]);
            foreach (array_chunk($rows, 50, true) as $chunk) {
                $inserts = [];
                foreach ($chunk as $line => $payload) {
                    $inserts[] = [
                        'employee_import_id' => $id,
                        'line' => $line,
                        'payload' => $payload === null ? null : Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
                    ];
                }
                DB::table('employee_import_rows')->insert($inserts);
            }

            return $id;
        }, 3);

        return $batchId;
    }

    public function process(User $actor, int $batch, bool $retry = false): void
    {
        Gate::forUser($actor)->authorize('import', Employee::class);
        $status = $retry ? 'failed' : 'pending';
        abort_unless(DB::table('employee_imports')->where('user_id', $actor->id)->where('created_at', '>=', now()->subDays(90))->find($batch), 404);
        $ids = DB::table('employee_import_rows')->where('employee_import_id', $batch)
            ->where('status', $status)->when($retry, fn ($query) => $query->whereNotNull('payload'))
            ->when($retry, fn ($query) => $query->orderBy('attempts'))
            ->orderBy('line')->limit(50)->get(['id', 'attempts']);

        foreach ($ids as $candidate) {
            DB::transaction(function () use ($actor, $batch, $candidate, $status): void {
                $actor = IdentityAccess::lockScopedActor($actor);
                Gate::forUser($actor)->authorize('import', Employee::class);
                $owned = DB::table('employee_imports')->where('user_id', $actor->id)
                    ->where('created_at', '>=', now()->subDays(90))->sharedLock()->find($batch);
                abort_unless($owned, 404);
                $row = DB::table('employee_import_rows')->where('employee_import_id', $batch)->lockForUpdate()->find($candidate->id);
                if (! $row || $row->status !== $status || $row->attempts !== $candidate->attempts) {
                    return;
                }

                $error = $row->payload === null ? 'import_row_invalid' : null;
                $employee = null;
                if ($error === null) {
                    try {
                        $employee = $this->employees->create($actor, json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR), importing: true);
                    } catch (AuthorizationException) {
                        $error = 'import_scope';
                    } catch (ValidationException $exception) {
                        $field = array_key_first($exception->errors());
                        $error = match ($field) {
                            'nik' => ($exception->errors()['nik'][0] === __('people.duplicate_nik')) ? 'duplicate_nik' : 'import_invalid_nik',
                            'department_id', 'home_location_id', 'position_id' => 'import_invalid_placement',
                            'joined_at' => 'import_invalid_date',
                            default => 'import_row_invalid',
                        };
                    }
                }

                DB::table('employee_import_rows')->where('id', $row->id)->update([
                    'status' => $error === null ? 'succeeded' : 'failed',
                    'attempts' => $row->attempts + 1,
                    'payload' => $error === null ? null : $row->payload,
                    'error_key' => $error,
                    'employee_id' => $employee?->id,
                ]);
            }, 3);
        }
    }
}
