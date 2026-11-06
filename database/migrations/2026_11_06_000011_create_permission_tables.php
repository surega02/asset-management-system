<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['permissions', 'roles'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 125);
                $table->string('guard_name', 32);
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        foreach (['roles' => 'role', 'permissions' => 'permission'] as $target => $key) {
            Schema::create('model_has_'.$target, function (Blueprint $table) use ($target, $key): void {
                $table->foreignId($key.'_id')->constrained($target)->cascadeOnDelete();
                $table->unsignedBigInteger('model_id');
                $table->string('model_type', 125);
                $table->index(['model_id', 'model_type']);
                $table->primary([$key.'_id', 'model_id', 'model_type']);
            });
        }

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['role_has_permissions', 'model_has_permissions', 'model_has_roles', 'roles', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
