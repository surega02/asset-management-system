<?php

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class MoveAssetLocation
{
    public function __construct(private AssetTargets $targets) {}

    public function __invoke(User $actor, Asset $asset, array $input): void
    {
        $data = Validator::make($input, [
            'location_history_id' => ['required', 'integer', 'min:1'],
            'physical_location_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:100'],
        ], attributes: [
            'location_history_id' => __('tracking.location_history'),
            'physical_location_id' => __('tracking.destination'),
            'reason' => __('people.fields.reason'),
        ])->validate();

        DB::transaction(function () use ($actor, $asset, $data): void {
            $actor = IdentityAccess::lockScopedActor($actor);
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $destination = (int) $data['physical_location_id'];
            Gate::forUser($actor)->authorize('move', [$asset, $destination]);
            if (! $asset->is_active) {
                throw ValidationException::withMessages(['physical_location_id' => __('tracking.inactive')]);
            }

            $previous = DB::table('asset_location_histories')->where('asset_id', $asset->id)
                ->orderByDesc('changed_at')->orderByDesc('id')->lockForUpdate()->first();
            if (! $previous || $previous->to_location_id !== $asset->physical_location_id) {
                throw new LogicException('Asset has no matching physical location history.');
            }
            if ($previous->id !== (int) $data['location_history_id']) {
                throw ValidationException::withMessages(['location_history_id' => __('tracking.stale_location')]);
            }
            if ($asset->physical_location_id === $destination) {
                throw ValidationException::withMessages(['physical_location_id' => __('tracking.same_location')]);
            }

            $this->targets->physical($actor, $destination, Assets::MOVE);
            $from = $asset->physical_location_id;
            $reason = trim($data['reason']);
            $asset->update(['physical_location_id' => $destination]);
            DB::table('asset_location_histories')->insert([
                'asset_id' => $asset->id, 'from_location_id' => $from, 'to_location_id' => $destination,
                'actor_id' => $actor->id, 'reason' => $reason,
                'changed_at' => now()->max(Carbon::parse($previous->changed_at)->addMicrosecond())->format('Y-m-d H:i:s.u'),
            ]);
            IdentityAudit::record($actor->id, $asset, 'asset.location_moved', $reason,
                ['physical_location_id' => $from], ['physical_location_id' => $destination]);
        }, 3);
    }
}
