<?php

namespace App\Providers;

use App\Models\Employee;
use App\Models\StructuralAppointment;
use App\Models\User;
use App\Policies\EmployeePolicy;
use App\Policies\OrganizationMasterPolicy;
use App\Policies\StructuralAppointmentPolicy;
use App\Policies\UserPolicy;
use App\Support\IdentityAccess;
use App\Support\MasterType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(StructuralAppointment::class, StructuralAppointmentPolicy::class);
        foreach (MasterType::cases() as $type) {
            Gate::policy($type->model(), OrganizationMasterPolicy::class);
        }
        Gate::define('manage-access-catalog', fn (User $actor): bool => IdentityAccess::superAdminCan($actor, 'roles.manage'));
        Gate::define('update-global-settings', fn (User $actor): bool => IdentityAccess::superAdminCan($actor, 'settings.manage'));
    }
}
