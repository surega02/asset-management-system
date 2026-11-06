<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\IdentityAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::define('manage-access-catalog', fn (User $actor): bool => IdentityAccess::superAdminCan($actor, 'roles.manage'));
        Gate::define('update-global-settings', fn (User $actor): bool => IdentityAccess::superAdminCan($actor, 'settings.manage'));
    }
}
