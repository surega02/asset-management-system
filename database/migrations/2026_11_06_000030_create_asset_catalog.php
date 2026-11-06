<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['asset_categories', 'asset_types', 'brands', 'vendors', 'asset_statuses', 'asset_conditions'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                if ($name === 'asset_categories') {
                    $table->foreignId('parent_id')->nullable()->constrained('asset_categories')->restrictOnDelete();
                }
                if ($name === 'asset_types') {
                    $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
                }
            });
        }

        Schema::create('asset_catalog_defaults', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('status_id')->constrained('asset_statuses')->restrictOnDelete();
            $table->foreignId('condition_id')->constrained('asset_conditions')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE asset_catalog_defaults ADD CONSTRAINT asset_catalog_defaults_singleton CHECK (id = 1)');

        // Seed the initial catalog once. Subsequent deployments preserve administrator choices.
        DB::table('asset_statuses')->insert(['code' => 'AVAILABLE', 'name' => 'Tersedia']);
        DB::table('asset_conditions')->insert(['code' => 'GOOD', 'name' => 'Baik']);
        DB::table('asset_catalog_defaults')->insert([
            'id' => 1,
            'status_id' => DB::table('asset_statuses')->where('code', 'AVAILABLE')->value('id'),
            'condition_id' => DB::table('asset_conditions')->where('code', 'GOOD')->value('id'),
        ]);

        Schema::create('attribute_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->string('key', 64);
            $table->unsignedInteger('version');
            $table->string('label');
            $table->string('data_type', 16);
            $table->json('allowed_options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('active_key', 64)->nullable()->storedAs('IF(is_active, '.chr(96).'key'.chr(96).', NULL)');
            $table->unique(['asset_type_id', 'key', 'version'], 'attribute_versions');
            $table->unique(['asset_type_id', 'active_key'], 'active_attribute_key');
            $table->unique(['id', 'asset_type_id'], 'attribute_definition_type');
        });

        Schema::create('asset_type_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('attribute_definition_id')->unique();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreign(['attribute_definition_id', 'asset_type_id'], 'type_attribute_definition')
                ->references(['id', 'asset_type_id'])->on('attribute_definitions')->restrictOnDelete();
            $table->index(['asset_type_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        foreach (['asset_type_attributes', 'attribute_definitions', 'asset_catalog_defaults', 'asset_types', 'asset_categories', 'brands', 'vendors', 'asset_statuses', 'asset_conditions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
