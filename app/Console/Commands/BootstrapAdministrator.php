<?php

namespace App\Console\Commands;

use App\Actions\Identity\PasswordResets;
use App\Models\Employee;
use App\Models\User;
use App\Support\IdentityAccess;
use App\Support\IdentityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BootstrapAdministrator extends Command
{
    protected $signature = 'ams:bootstrap-admin {nik : NIK of a pre-provisioned employee}';

    protected $description = 'Provision the first Super Admin and request a verified password handover';

    public function handle(PasswordResets $resets): int
    {
        $created = DB::transaction(function () use ($resets): bool {
            $role = Role::query()->where('name', IdentityAccess::SUPER_ADMIN)->where('guard_name', 'web')->lockForUpdate()->first();
            $employee = Employee::query()->where('nik', trim($this->argument('nik')))->lockForUpdate()->first();

            if (! $role || ! $employee || $employee->user()->exists() || User::role($role)->exists()) {
                return false;
            }

            $account = $employee->user()->create(['password' => Str::random(64)]);
            $account->assignRole($role);
            IdentityAudit::record(null, $account, 'account.bootstrap', 'initial_operator_provisioning', new: [
                'employee_id' => $account->employee_id,
                'role_ids' => [$role->id],
            ]);
            $resets->requestLocked($account, null, 'initial_operator_provisioning');

            return true;
        }, 3);

        if (! $created) {
            $this->error('Bootstrap requires seeded roles, an employee without an account, and no existing Super Admin.');

            return self::FAILURE;
        }

        $this->info('Account created. Verify the employee before issuing a password code.');

        return self::SUCCESS;
    }
}
