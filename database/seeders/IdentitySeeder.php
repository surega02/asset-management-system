<?php

namespace Database\Seeders;

use App\Support\IdentityAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (IdentityAccess::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (IdentityAccess::ROLES as $name) {
            Role::findOrCreate($name, 'web');
        }

        Role::findByName(IdentityAccess::SUPER_ADMIN, 'web')->givePermissionTo(IdentityAccess::PERMISSIONS);
    }
}
