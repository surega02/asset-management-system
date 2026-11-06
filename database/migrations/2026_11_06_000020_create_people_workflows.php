<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            foreach (['from', 'to'] as $side) {
                foreach (['branch', 'division', 'department'] as $unit) {
                    $table->foreignId($side.'_'.$unit.'_id')->constrained($unit === 'branch' ? 'branches' : $unit.'s')->restrictOnDelete();
                }
                $table->foreignId($side.'_home_location_id')->constrained('locations')->restrictOnDelete();
            }
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->timestamp('changed_at');
            $table->index(['employee_id', 'changed_at']);
        });

        Schema::create('structural_appointment_scope_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('role_kind', 16);
            $table->string('scope_type', 16);
            $table->unsignedBigInteger('scope_id');
            $table->unique(['role_kind', 'scope_type', 'scope_id'], 'appointment_scope_key');
        });

        Schema::create('structural_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->string('role_kind', 16);
            $table->string('scope_type', 16);
            foreach (['branch', 'division', 'department'] as $scope) {
                $table->foreignId($scope.'_id')->nullable()->constrained()->restrictOnDelete();
            }
            $table->string('appointment_kind', 16);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('acting_for_appointment_id')->nullable()->constrained('structural_appointments')->restrictOnDelete();
            $table->timestamps();
            $table->index(['role_kind', 'scope_type', 'appointment_kind', 'starts_on', 'ends_on'], 'appointment_effective_period');
        });

        DB::statement("ALTER TABLE structural_appointments
            ADD CONSTRAINT appointment_date_range CHECK (ends_on IS NULL OR ends_on >= starts_on),
            ADD CONSTRAINT appointment_kind_valid CHECK (appointment_kind IN ('definitive', 'acting')),
            ADD CONSTRAINT appointment_scope_valid CHECK (
                (role_kind = 'hod' AND scope_type = 'department' AND department_id IS NOT NULL AND division_id IS NULL AND branch_id IS NULL)
                OR (role_kind = 'director' AND department_id IS NULL AND (
                    (scope_type = 'company' AND division_id IS NULL AND branch_id IS NULL)
                    OR (scope_type = 'branch' AND division_id IS NULL AND branch_id IS NOT NULL)
                    OR (scope_type = 'division' AND division_id IS NOT NULL AND branch_id IS NULL)
                ))
            )");

        Schema::create('employee_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('fingerprint', 64);
            $table->timestamp('created_at');
            $table->unique(['user_id', 'fingerprint']);
            $table->index('created_at');
        });

        Schema::create('employee_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->text('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error_key', 80)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->unique(['employee_import_id', 'line']);
        });
    }

    public function down(): void
    {
        foreach (['employee_import_rows', 'employee_imports', 'structural_appointments', 'structural_appointment_scope_locks', 'employee_movements'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
