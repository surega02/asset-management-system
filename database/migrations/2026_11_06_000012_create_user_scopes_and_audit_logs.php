<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_data_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('scope_type', 12);
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            foreach (['branch_id', 'division_id', 'department_id'] as $column) {
                $table->unique(['user_id', $column]);
            }
        });
        DB::statement(<<<'SQL'
            ALTER TABLE user_data_scopes ADD CONSTRAINT user_data_scopes_target CHECK (
                (scope_type = 'branch' AND branch_id IS NOT NULL AND division_id IS NULL AND department_id IS NULL)
                OR (scope_type = 'division' AND branch_id IS NULL AND division_id IS NOT NULL AND department_id IS NULL)
                OR (scope_type = 'department' AND branch_id IS NULL AND division_id IS NULL AND department_id IS NOT NULL)
            )
            SQL);

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 80);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 100);
            $table->timestamp('created_at');
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_data_scopes');
    }
};
