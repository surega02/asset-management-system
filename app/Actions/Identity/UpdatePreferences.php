<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;

final class UpdatePreferences
{
    public static function save(User $actor, array $preferences): void
    {
        DB::transaction(function () use ($actor, $preferences): void {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->is_active && $actor->auth_version === $user->auth_version, 403);
            $before = $user->only(['locale', 'theme']);
            $user->fill(array_intersect_key($preferences, $before));

            if ($user->isDirty(['locale', 'theme'])) {
                $user->save();
                IdentityAudit::record($user->id, $user, 'account.preferences_changed', 'self_service', $before, $user->only(['locale', 'theme']));
            }
        }, 3);
    }
}
