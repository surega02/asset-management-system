<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_number_sequences', function (Blueprint $table): void {
            $table->string('key', 16)->primary();
            $table->unsignedBigInteger('next_number');
        });
        DB::table('asset_number_sequences')->insert(['key' => 'AST', 'next_number' => 1]);

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('asset_code', 32)->unique();
            $table->char('public_token', 64)->collation('ascii_bin')->charset('ascii')->unique();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('status_id')->constrained('asset_statuses')->restrictOnDelete();
            $table->foreignId('condition_id')->constrained('asset_conditions')->restrictOnDelete();
            $table->foreignId('physical_location_id')->constrained('locations')->restrictOnDelete();
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->decimal('acquisition_cost', 18, 2);
            $table->date('purchase_date');
            $table->string('invoice_number')->nullable();
            $table->date('warranty_ends_on')->nullable();
            $table->boolean('is_active');
            $table->timestamp('registered_at', 6);
            $table->timestamp('updated_at', 6);
            $table->index(['physical_location_id', 'is_active']);
            $table->index(['asset_type_id', 'status_id']);
            $table->index('purchase_date');
            $table->index('registered_at');
            $table->index('updated_at');
        });
        DB::statement('ALTER TABLE assets ADD CONSTRAINT asset_cost_nonnegative CHECK (acquisition_cost >= 0)');

        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('started_at', 6);
            $table->timestamp('ended_at', 6)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 100);
            $table->unsignedBigInteger('active_asset_id')->nullable()->storedAs('IF(ended_at IS NULL, asset_id, NULL)')->unique();
            foreach (['asset_id', 'employee_id', 'department_id', 'location_id'] as $key) {
                $table->index([$key, 'ended_at']);
            }
        });
        DB::statement('ALTER TABLE asset_assignments
            ADD CONSTRAINT asset_assignment_one_target CHECK ((employee_id IS NOT NULL) + (department_id IS NOT NULL) + (location_id IS NOT NULL) = 1),
            ADD CONSTRAINT asset_assignment_valid_period CHECK (ended_at IS NULL OR ended_at > started_at)');

        Schema::create('asset_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('attribute_definition_id')->constrained()->restrictOnDelete();
            $table->json('value_json');
            $table->unique(['asset_id', 'attribute_definition_id'], 'asset_attribute_version');
        });

        Schema::create('asset_location_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 100);
            $table->timestamp('changed_at', 6);
            $table->index(['asset_id', 'changed_at']);
        });

        Schema::create('asset_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('disk', 24);
            $table->string('storage_path')->unique();
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['id', 'asset_id']);
        });

        Schema::create('asset_deactivations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('deactivated_at', 6);
            $table->string('reason', 100);
            $table->unsignedBigInteger('attachment_id')->nullable();
            $table->foreign(['attachment_id', 'asset_id'])->references(['id', 'asset_id'])->on('asset_attachments')->restrictOnDelete();
            $table->index(['asset_id', 'deactivated_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropForeign(['asset_id']);
            $table->dropIndex(['asset_id', 'created_at']);
            $table->dropColumn('asset_id');
        });
        foreach (['asset_deactivations', 'asset_attachments', 'asset_location_histories', 'asset_attribute_values', 'asset_assignments', 'assets', 'asset_number_sequences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
