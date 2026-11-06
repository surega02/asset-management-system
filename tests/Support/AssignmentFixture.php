<?php

namespace Tests\Support;

use App\Actions\Employees\DirectEmployeeAssignments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class AssignmentFixture
{
    public static function migrate(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->foreignId('physical_location_id')->constrained('locations')->restrictOnDelete();
            $table->boolean('is_active');
        });
        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('active_asset_id')->storedAs('IF(ended_at IS NULL, asset_id, NULL)')->unique();
            $table->index(['employee_id', 'ended_at']);
        });
        DB::statement('ALTER TABLE asset_assignments ADD CONSTRAINT assignment_fixture_target CHECK
            ((employee_id IS NOT NULL) + (department_id IS NOT NULL) + (location_id IS NOT NULL) = 1),
            ADD CONSTRAINT assignment_fixture_range CHECK (ended_at IS NULL OR ended_at > started_at)');
    }

    public static function assign(int $employee, int $location): int
    {
        return DB::transaction(function () use ($employee, $location): int {
            DirectEmployeeAssignments::lockEligibleAssignee($employee);
            $asset = DB::table('assets')->insertGetId([
                'asset_code' => 'M04-'.Str::random(12), 'name' => 'Aset sintetis', 'physical_location_id' => $location, 'is_active' => true,
            ]);
            DB::table('asset_assignments')->insert(['asset_id' => $asset, 'employee_id' => $employee, 'started_at' => now()]);

            return $asset;
        });
    }
}
