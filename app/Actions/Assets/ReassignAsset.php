<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ReassignAsset
{
    public function __construct(private AssetTargets $targets) {}

    public function __invoke(User $actor, Asset $asset, array $input): void
    {
        $data = Validator::make($input, [
            'assignment_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:100'],
        ], attributes: ['assignment_id' => __('tracking.assignment'), 'reason' => __('people.fields.reason')])->validate();

        DB::transaction(function () use ($actor, $asset, $data, $input): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            Gate::forUser($actor)->authorize('reassign', $asset);
            if (! $asset->is_active) {
                throw ValidationException::withMessages(['assignment_id' => __('tracking.inactive')]);
            }

            $previous = $asset->activeAssignment()->lockForUpdate()->first()
                ?? throw new LogicException('Active asset has no active assignment.');
            if ($previous->id !== (int) $data['assignment_id']) {
                throw ValidationException::withMessages(['assignment_id' => __('tracking.stale_assignment')]);
            }

            $target = $this->targets->validate($actor, Assets::REASSIGN, $input);
            $key = array_key_first($target);
            if ($previous->{$key} === $target[$key]) {
                throw ValidationException::withMessages(['assignee' => __('tracking.same_assignee')]);
            }

            $reason = trim($data['reason']);
            $changedAt = now()->max($previous->started_at->addMicrosecond());
            $previous->update(['ended_at' => $changedAt]);
            $next = $asset->activeAssignment()->create($target + [
                'actor_id' => $actor->id, 'started_at' => $changedAt, 'reason' => $reason,
            ]);
            $asset->touch();
            IdentityAudit::record($actor->id, $asset, 'asset.reassigned', $reason,
                $previous->only(AssetTargets::KEYS) + ['assignment_id' => $previous->id],
                $next->only(AssetTargets::KEYS) + ['assignment_id' => $next->id]);
        }, 3);
    }
}
