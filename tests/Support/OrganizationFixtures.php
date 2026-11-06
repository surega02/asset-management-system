<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrganizationFixtures
{
    public static function tree(): array
    {
        $code = Str::lower(Str::random(12));
        $branch = self::insert('branches', $code, 'Cabang '.$code);
        $division = self::insert('divisions', $code, 'Divisi '.$code, ['branch_id' => $branch]);
        $department = self::insert('departments', $code, 'Departemen '.$code, ['division_id' => $division]);
        $location = self::insert('locations', $code, 'Lokasi '.$code, ['branch_id' => $branch]);
        $position = self::insert('positions', $code, 'Jabatan '.$code);

        return compact('code', 'branch', 'division', 'department', 'location', 'position');
    }

    public static function insert(string $table, string $code, string $name, array $attributes = []): int
    {
        return DB::table($table)->insertGetId(['code' => $code, 'name' => $name] + $attributes);
    }

    public static function actor(array $tree, ?string $scope = null, array $permissions = []): User
    {
        $employee = DB::table('employees')->insertGetId([
            'nik' => 'M03-'.Str::lower(Str::random(12)), 'name' => 'Petugas Master',
            'department_id' => $tree['department'], 'position_id' => $tree['position'],
            'home_location_id' => $tree['location'], 'joined_at' => '2024-01-01', 'employment_status' => 'active',
        ]);
        $user = User::query()->create(['employee_id' => $employee, 'password' => Str::random(32)]);
        if ($scope !== null) {
            DB::table('user_data_scopes')->insert(['user_id' => $user->id, 'scope_type' => $scope, $scope.'_id' => $tree[$scope]]);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }

    public static function data(string $code, array $overrides = []): array
    {
        return $overrides + ['master_code' => $code, 'name' => 'Master '.$code, 'is_active' => 1];
    }
}
