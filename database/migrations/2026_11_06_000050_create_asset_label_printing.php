<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_label_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->decimal('width_mm', 5, 2);
            $table->decimal('height_mm', 5, 2);
            $table->json('allowed_fields');
            $table->boolean('is_active')->default(true);
        });
        DB::statement('ALTER TABLE asset_label_templates ADD CONSTRAINT asset_label_size CHECK (width_mm BETWEEN 60 AND 120 AND height_mm BETWEEN 36 AND 75)');
        foreach (['compact' => [60, 36], 'standard' => [80, 45], 'large' => [100, 60]] as $name => [$width, $height]) {
            DB::table('asset_label_templates')->insert([
                'name' => $name, 'width_mm' => $width, 'height_mm' => $height,
                'allowed_fields' => json_encode(['name', 'category', 'status'], JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::create('asset_label_print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('asset_label_templates')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('selected_fields');
            $table->string('status', 24);
            $table->timestamp('created_at');
            $table->index(['requested_by', 'created_at']);
        });

        Schema::create('asset_label_print_job_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained('asset_label_print_jobs')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->unique(['job_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        foreach (['asset_label_print_job_items', 'asset_label_print_jobs', 'asset_label_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
