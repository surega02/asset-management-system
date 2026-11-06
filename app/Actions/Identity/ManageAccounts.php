<?php

namespace App\Actions\Identity;

use App\Models\Employee;
use App\Models\User;
use App\Queries\OrganizationScope;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class ManageAccounts
{
    public function __construct(private PasswordResets $resets) {}

    public function create(User $actor, Employee $employee, ?string $email): User
    {
        return DB::transaction(function () use ($actor, $employee, $email): User {
            $actor = IdentityAccess::lockAdministrator($actor);
            $employee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            Gate::forUser($actor)->authorize('create', [User::class, $employee]);

            if ($employee->user()->exists()) {
                throw ValidationException::withMessages(['employee_id' => __('iam.account_exists')]);
            }

            $this->ensureUniqueEmail($email);
            $account = $employee->user()->create(['email' => $email, 'password' => Str::random(64)]);
            IdentityAudit::record($actor->id, $account, 'account.created', 'administrative_provisioning', new: $this->profile($account));

            return $account;
        }, 3);
    }

    public function update(User $actor, User $account, ?string $email, bool $active): void
    {
        DB::transaction(function () use ($actor, $account, $email, $active): void {
            $actor = IdentityAccess::lockAdministrator($actor);
            $account = $this->lockAccount($account);
            Gate::forUser($actor)->authorize('manage', $account);
            $this->ensureUniqueEmail($email, $account);
            $before = $this->profile($account);

            if (! $active && $account->hasRole(IdentityAccess::SUPER_ADMIN)) {
                $this->requireAnotherSuperAdmin($account);
            }

            $account->fill(['email' => $email, 'is_active' => $active]);
            if (! $account->isDirty(['email', 'is_active'])) {
                return;
            }

            $account->auth_version++;
            $account->save();
            DB::table('password_reset_tokens')->where('user_id', $account->id)->delete();
            IdentityAudit::record($actor->id, $account, 'account.updated', 'administrative_update', $before, $this->profile($account));
        }, 3);
    }

    public function reset(User $actor, User $account): void
    {
        DB::transaction(function () use ($actor, $account): void {
            $actor = IdentityAccess::lockAdministrator($actor);
            $account = $this->lockAccount($account);
            Gate::forUser($actor)->authorize('reset', $account);
            $this->resets->requestLocked($account, $actor->id, 'administrative_reset');
        }, 3);
    }

    public function access(User $actor, User $account, array $roleIds, array $scopes): void
    {
        DB::transaction(function () use ($actor, $account, $roleIds, $scopes): void {
            $actor = IdentityAccess::lockAdministrator($actor);
            $account = $this->lockAccount($account);
            Gate::forUser($actor)->authorize('access', $account);

            $roles = Role::query()->where('guard_name', 'web')->whereKey($roleIds)->get();
            if ($roles->count() !== count($roleIds)) {
                throw ValidationException::withMessages(['roles' => __('iam.invalid_access')]);
            }
            if ($account->is_active && $account->hasRole(IdentityAccess::SUPER_ADMIN) && ! $roles->contains('name', IdentityAccess::SUPER_ADMIN)) {
                $this->requireAnotherSuperAdmin($account);
            }

            $before = $this->accessSnapshot($account);
            $account->syncRoles($roles);
            DB::table('user_data_scopes')->where('user_id', $account->id)->delete();

            foreach ($scopes as $scope) {
                [$type, $id] = explode(':', $scope, 2);
                $table = OrganizationScope::TABLES[$type] ?? null;
                $target = $table ? DB::table($table)->where('id', $id)->lockForUpdate()->first(['id']) : null;
                if (! $target) {
                    throw ValidationException::withMessages(['scopes' => __('iam.invalid_access')]);
                }

                DB::table('user_data_scopes')->insert(['user_id' => $account->id, 'scope_type' => $type, $type.'_id' => $target->id]);
            }

            $after = $this->accessSnapshot($account);
            if ($before !== $after) {
                $account->increment('auth_version');
                IdentityAudit::record($actor->id, $account, 'account.access_changed', 'administrative_access', $before, $after);
            }
        }, 3);
    }

    private function lockAccount(User $account): User
    {
        Employee::query()->lockForUpdate()->findOrFail($account->employee_id);

        return User::query()->lockForUpdate()->findOrFail($account->id);
    }

    private function requireAnotherSuperAdmin(User $account): void
    {
        if (! User::role(IdentityAccess::SUPER_ADMIN)->where('is_active', true)->whereKeyNot($account->id)->exists()) {
            throw ValidationException::withMessages(['account' => __('iam.last_super_admin')]);
        }
    }

    private function ensureUniqueEmail(?string $email, ?User $except = null): void
    {
        if ($email !== null && User::query()->where('email', $email)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))->exists()) {
            throw ValidationException::withMessages(['email' => __('iam.email_in_use')]);
        }
    }

    private function profile(User $account): array
    {
        return ['employee_id' => $account->employee_id, 'is_active' => $account->is_active, 'has_email' => $account->email !== null];
    }

    private function accessSnapshot(User $account): array
    {
        return [
            'role_ids' => $account->roles()->orderBy('roles.id')->pluck('roles.id')->all(),
            'scope_ids' => DB::table('user_data_scopes')->where('user_id', $account->id)
                ->orderBy('scope_type')->orderBy('branch_id')->orderBy('division_id')->orderBy('department_id')->get()
                ->map(fn ($scope) => $scope->scope_type.':'.$scope->{$scope->scope_type.'_id'})->all(),
        ];
    }
}
