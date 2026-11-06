<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Queries\ManagedAccounts;
use App\Support\IdentityAccess;
use Illuminate\Support\Facades\DB;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->checkPermissionTo('users.view');
    }

    public function view(User $actor, User $account): bool
    {
        return ManagedAccounts::users($actor)->whereKey($account->getKey())->exists();
    }

    public function create(User $actor, Employee $employee): bool
    {
        return $actor->checkPermissionTo('users.manage')
            && ManagedAccounts::employees($actor)->whereKey($employee->getKey())->exists();
    }

    public function manage(User $actor, User $account): bool
    {
        return $actor->checkPermissionTo('users.manage') && $this->canOperate($actor, $account);
    }

    public function reset(User $actor, User $account): bool
    {
        return $actor->checkPermissionTo('users.reset') && $account->is_active && $this->canOperate($actor, $account);
    }

    public function access(User $actor, User $account): bool
    {
        return ! $actor->is($account)
            && IdentityAccess::superAdminCan($actor, 'users.access')
            && $this->view($actor, $account);
    }

    private function canOperate(User $actor, User $account): bool
    {
        if ($actor->is($account) || ! $this->view($actor, $account)) {
            return false;
        }

        return IdentityAccess::superAdminCan($actor, 'users.access') || (
            ! $account->roles()->exists()
            && ! $account->permissions()->exists()
            && ! DB::table('user_data_scopes')->where('user_id', $account->id)->exists()
        );
    }
}
