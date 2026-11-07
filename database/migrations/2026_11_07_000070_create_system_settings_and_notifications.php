<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key')->unique();
            $table->json('setting_value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('updated_at');
        });
        DB::table('system_settings')->insert(['setting_key' => 'default_locale', 'setting_value' => '"id"', 'updated_at' => now()]);

        Schema::create('notification_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->string('kind', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('old_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('new_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('ends_on')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('published_at')->nullable();
            $table->index(['published_at', 'id']);
        });
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('notification_event_id')->constrained()->restrictOnDelete();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at');
            $table->unique(['user_id', 'notification_event_id']);
            $table->index(['user_id', 'id']);
        });
        Schema::table('audit_logs', fn (Blueprint $table) => $table->index(['actor_id', 'created_at']));
    }

    public function down(): void
    {
        // MySQL can replace the implicit foreign-key index with the composite index.
        if (! Schema::hasIndex('audit_logs', ['actor_id'])) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->index('actor_id'));
        }
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex(['actor_id', 'created_at']));
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('system_settings');
    }
};
