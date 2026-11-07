<?php

namespace App\Actions\Imports;

use App\Models\ImportRow;
use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ProcessImportRow
{
    public const LEASE_SECONDS = 120;

    private const MAX_RECOVERIES = 2;

    public function __construct(private ImportAssetMutation $mutation, private AssetImportBatches $imports) {}

    public function claim(int $id): ?ImportRow
    {
        return DB::transaction(function () use ($id): ?ImportRow {
            $batch = AssetImportBatches::recent()->where('status', 'running')->lockForUpdate()->find($id);
            if ($batch === null) {
                return null;
            }
            // One active lease per batch keeps repeated updates to the same code in spreadsheet order.
            $rows = ImportRow::query()->where('import_batch_id', $id);
            $row = (clone $rows)->where('status', 'processing')->lockForUpdate()->first();
            if ($row !== null) {
                if ($row->lease_expires_at > now()->toDateTimeString()) {
                    return null;
                }
                if ($row->recoveries >= self::MAX_RECOVERIES) {
                    $this->finish($row, $batch->user_id, 'failed', errors: ['key' => 'interrupted']);
                    $row = null;
                } else {
                    $this->attempt($row)->update(['outcome' => 'interrupted', 'error_summary' => 'interrupted']);
                    $row->recoveries++;
                }
            }
            $row ??= (clone $rows)->where('status', 'pending')->orderBy('row_number')->lockForUpdate()->first();
            if ($row === null) {
                DB::table('import_batches')->where('id', $id)->update([
                    'status' => 'finished', 'finished_at' => now(), 'next_dispatch_at' => null,
                ]);

                return null;
            }
            $row->fill([
                'status' => 'processing', 'attempt_count' => $row->attempt_count + 1,
                'processing_started_at' => now(), 'lease_expires_at' => now()->addSeconds(self::LEASE_SECONDS),
            ])->save();
            DB::table('import_row_attempts')->insert([
                'import_row_id' => $row->id, 'attempt_number' => $row->attempt_count, 'outcome' => 'processing',
                'payload_hash' => hash('sha256', json_encode($row->payload, JSON_THROW_ON_ERROR)), 'attempted_at' => now(),
            ]);

            return $row;
        }, 3);
    }

    public function execute(ImportRow $claim): void
    {
        try {
            DB::transaction(function () use ($claim): void {
                $row = $this->ownedLease($claim, unexpired: true);
                if ($row === null) {
                    return;
                }
                $batch = AssetImportBatches::recent()->find($row->import_batch_id);
                if ($batch === null) {
                    return;
                }
                if ($row->action === 'skip') {
                    $this->finish($row, $batch->user_id, 'skipped', errors: $row->payload === null ? ['key' => 'invalid_row'] : null);

                    return;
                }
                $actor = IdentityAccess::lockScopedActor(User::query()->findOrFail($batch->user_id));
                $row->possible_duplicate = $this->imports->similar($actor, $row);
                $asset = $this->mutation->apply($actor, $row->payload, $row->action);
                IdentityAudit::record($actor->id, $asset, 'asset.imported', $this->auditReason($row), new: [
                    'asset_code' => $asset->asset_code, 'import_batch_id' => $row->import_batch_id,
                    'import_row_number' => $row->row_number, 'import_action' => $row->action,
                ]);
                $this->finish($row, $actor->id, 'succeeded', $asset->id);
            }, 3);
        } catch (ValidationException $exception) {
            $this->recordFailure($claim, ['key' => 'invalid_fields', 'fields' => array_keys($exception->errors())]);
        } catch (AuthorizationException $exception) {
            $this->recordFailure($claim, ['key' => 'unavailable']);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 403) {
                throw $exception;
            }
            $this->recordFailure($claim, ['key' => 'unavailable']);
        }
    }

    private function recordFailure(ImportRow $claim, array $errors): void
    {
        // The business transaction has already rolled back. Fence off any superseded attempt.
        DB::transaction(function () use ($claim, $errors): void {
            $row = $this->ownedLease($claim);
            if ($row && ($batch = AssetImportBatches::recent()->find($row->import_batch_id))) {
                $this->finish($row, $batch->user_id, 'failed', errors: $errors);
            }
        }, 3);
    }

    private function ownedLease(ImportRow $claim, bool $unexpired = false): ?ImportRow
    {
        return ImportRow::query()->where('status', 'processing')->where('attempt_count', $claim->attempt_count)
            ->when($unexpired, fn ($query) => $query->where('lease_expires_at', '>', now()))
            ->lockForUpdate()->find($claim->id);
    }

    private function finish(ImportRow $row, int $actor, string $status, ?int $asset = null, ?array $errors = null): void
    {
        $row->update([
            'status' => $status, 'asset_id' => $asset, 'errors' => $errors, 'processed_at' => now(),
            'lease_expires_at' => null, 'processing_started_at' => null,
        ]);
        $this->attempt($row)->update(['outcome' => $status, 'error_summary' => $errors['key'] ?? null]);
        if ($status !== 'succeeded') {
            IdentityAudit::record($actor, $row, 'asset_import.'.$status, $errors['key'] ?? 'skip-selected', new: [
                'import_batch_id' => $row->import_batch_id, 'import_row_number' => $row->row_number, 'import_action' => $row->action,
            ]);
        }
    }

    private function attempt(ImportRow $row): Builder
    {
        return DB::table('import_row_attempts')->where('import_row_id', $row->id)->where('attempt_number', $row->attempt_count);
    }

    private function auditReason(ImportRow $row): string
    {
        return 'asset-import:'.$row->import_batch_id.':'.$row->row_number;
    }
}
