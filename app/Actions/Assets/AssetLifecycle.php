<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCondition;
use App\Models\AssetStatus;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use App\Support\SystemNotifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AssetLifecycle
{
    public function __construct(private AssetTargets $targets, private AssetFiles $files) {}

    public function deactivate(User $actor, Asset $asset, array $input, ?UploadedFile $document = null): void
    {
        Gate::forUser($actor)->authorize('lifecycle', $asset);
        $reason = $this->reason($input);
        $this->files->run(['deactivation' => $document], fn (array $files) => DB::transaction(function () use ($actor, $asset, $reason, $files): void {
            [$actor, $asset] = $this->lock($actor, $asset, 'lifecycle', expectedActive: true);
            $assignment = $asset->activeAssignment()->lockForUpdate()->first()
                ?? throw new LogicException('Active asset has no active assignment.');
            $endedAt = now()->max($assignment->started_at->addMicrosecond());
            $before = $asset->getAttributes() + $assignment->only(AssetTargets::KEYS) + ['assignment_id' => $assignment->id];
            $assignment->update(['ended_at' => $endedAt]);
            $asset->update(['is_active' => false]);
            $ids = $this->files->attach($asset, $actor, $files);
            DB::table('asset_deactivations')->insert([
                'asset_id' => $asset->id, 'actor_id' => $actor->id, 'reason' => $reason,
                'deactivated_at' => $endedAt->format('Y-m-d H:i:s.u'), 'attachment_id' => $ids['deactivation'] ?? null,
            ]);
            $auditId = IdentityAudit::record($actor->id, $asset, 'asset.deactivated', $reason, $before, $asset->getAttributes() + ['attachment_ids' => $ids]);
            SystemNotifications::record('asset_deactivated', $asset->id, 'audit:'.$auditId);
        }, 3));
    }

    public function reactivate(User $actor, Asset $asset, array $input): void
    {
        DB::transaction(function () use ($actor, $asset, $input): void {
            [$actor, $asset] = $this->lock($actor, $asset, 'lifecycle', expectedActive: false);
            $reason = $this->reason($input);
            if ($asset->activeAssignment()->lockForUpdate()->first()) {
                throw new LogicException('Inactive asset has an active assignment.');
            }
            $this->targets->physical($actor, $asset->physical_location_id, Assets::LIFECYCLE);
            $state = $this->state($input);
            $target = $this->targets->validate($actor, Assets::LIFECYCLE, $input);
            $lastEnd = AssetAssignment::query()->where('asset_id', $asset->id)->whereNotNull('ended_at')
                ->orderByDesc('ended_at')->lockForUpdate()->first(['ended_at'])?->ended_at;
            $startedAt = $lastEnd ? now()->max($lastEnd) : now();
            $assignment = $asset->activeAssignment()->create($target + ['actor_id' => $actor->id, 'started_at' => $startedAt, 'reason' => $reason]);
            $before = $asset->getAttributes();
            $asset->update($state + ['is_active' => true]);
            $auditId = IdentityAudit::record($actor->id, $asset, 'asset.reactivated', $reason, $before,
                $asset->getAttributes() + $target + ['assignment_id' => $assignment->id]);
            SystemNotifications::record('asset_reactivated', $asset->id, 'audit:'.$auditId);
        }, 3);
    }

    public function updateState(User $actor, Asset $asset, array $input): void
    {
        DB::transaction(function () use ($actor, $asset, $input): void {
            [$actor, $asset] = $this->lock($actor, $asset, 'updateState', expectedActive: true);
            $state = $this->state($input);
            $reason = $this->reason($input);
            $before = $asset->getAttributes();
            $asset->fill($state);
            if ($asset->isDirty()) {
                $asset->save();
                IdentityAudit::record($actor->id, $asset, 'asset.state_changed', $reason, $before, $asset->getAttributes());
            }
        }, 3);
    }

    private function lock(User $actor, Asset $asset, string $ability, bool $expectedActive): array
    {
        $actor = IdentityAccess::lockScopedActor($actor);
        $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
        Gate::forUser($actor)->authorize($ability, $asset);
        if ($asset->is_active !== $expectedActive) {
            throw ValidationException::withMessages(['is_active' => __('assets.lifecycle_conflict')]);
        }

        return [$actor, $asset];
    }

    private function reason(array $input): string
    {
        if (isset($input['reason']) && is_string($input['reason'])) {
            $input['reason'] = trim($input['reason']);
        }

        return Validator::make($input, ['reason' => ['required', 'string', 'max:100']], attributes: ['reason' => __('people.fields.reason')])->validate()['reason'];
    }

    private function state(array $input): array
    {
        $data = Validator::make($input, array_fill_keys(['status_id', 'condition_id'], ['required', 'integer', 'min:1']),
            attributes: __('assets.fields'))->validate();
        foreach (['status_id' => AssetStatus::class, 'condition_id' => AssetCondition::class] as $key => $model) {
            if (! $model::query()->where('is_active', true)->sharedLock()->find($data[$key])) {
                throw ValidationException::withMessages([$key => __('assets.invalid_reference')]);
            }
        }

        return $data;
    }
}
