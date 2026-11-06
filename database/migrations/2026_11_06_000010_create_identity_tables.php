<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['branch_id', 'code']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['division_id', 'code']);
        });

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['branch_id', 'code']);
            $table->unique(['id', 'branch_id']);
            $table->foreign(['parent_id', 'branch_id'])->references(['id', 'branch_id'])->on('locations')->restrictOnDelete();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('nik', 64)->collation('utf8mb4_0900_as_ci')->unique();
            $table->string('name');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_location_id')->constrained('locations')->restrictOnDelete();
            $table->date('joined_at');
            $table->string('employment_status', 32);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_nik_unambiguous CHECK (CHAR_LENGTH(TRIM(nik)) > 0 AND LOCATE('@', nik) = 0 AND CHAR_LENGTH(nik) = CHAR_LENGTH(TRIM(nik)))");

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->restrictOnDelete();
            $table->string('email', 254)->nullable()->collation('utf8mb4_0900_as_ci')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('auth_version')->default(1);
            $table->string('locale', 10)->default('id');
            $table->string('theme', 5)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->json('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('created_at');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        foreach (['sessions', 'password_reset_tokens', 'users', 'employees', 'locations', 'positions', 'departments', 'divisions', 'branches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
