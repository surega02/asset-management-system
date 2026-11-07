<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Queries\Assets;

final class AssetPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->checkPermissionTo(Assets::VIEW);
    }

    public function view(User $actor, Asset $asset): bool
    {
        return Assets::visible($actor)->whereKey($asset->id)->exists();
    }

    public function create(User $actor, ?int $locationId = null): bool
    {
        return $this->viewAny($actor) && Assets::locations($actor, Assets::CREATE)
            ->when($locationId !== null, fn ($query) => $query->whereKey($locationId))->exists();
    }

    public function lifecycle(User $actor, Asset $asset): bool
    {
        return $this->canOperate($actor, $asset, Assets::LIFECYCLE);
    }

    public function import(User $actor): bool
    {
        return $this->viewAny($actor) && Assets::locations($actor, Assets::IMPORT)->exists();
    }

    public function update(User $actor, Asset $asset): bool
    {
        return $asset->is_active && $this->canOperate($actor, $asset, Assets::UPDATE);
    }

    public function updateState(User $actor, Asset $asset): bool
    {
        return $this->canOperate($actor, $asset, Assets::UPDATE_STATE);
    }

    public function reassign(User $actor, Asset $asset): bool
    {
        return $this->canOperate($actor, $asset, Assets::REASSIGN);
    }

    public function printAny(User $actor): bool
    {
        return $this->viewAny($actor) && Assets::locations($actor, Assets::PRINT_LABEL)->exists();
    }

    public function printLabel(User $actor, Asset $asset): bool
    {
        return $this->canOperate($actor, $asset, Assets::PRINT_LABEL);
    }

    public function move(User $actor, Asset $asset, ?int $destination = null): bool
    {
        return $this->canOperate($actor, $asset, Assets::MOVE)
            && ($destination === null || Assets::locations($actor, Assets::MOVE)->whereKey($destination)->exists());
    }

    private function canOperate(User $actor, Asset $asset, string $permission): bool
    {
        return $this->viewAny($actor) && Assets::locations($actor, $permission)->whereKey($asset->physical_location_id)->exists();
    }
}
