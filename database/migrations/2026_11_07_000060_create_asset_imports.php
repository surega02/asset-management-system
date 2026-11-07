<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedSmallInteger('total_rows');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('next_dispatch_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['status', 'next_dispatch_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('action', 8);
            $table->string('status', 16);
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('payload')->nullable();
            $table->json('errors')->nullable();
            $table->boolean('possible_duplicate')->default(false);
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('recoveries')->default(0);
            $table->dateTime('processing_started_at')->nullable();
            $table->dateTime('lease_expires_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'status']);
            $table->index(['status', 'lease_expires_at']);
        });
        Schema::create('import_row_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_row_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('outcome', 16);
            $table->string('error_summary', 255)->nullable();
            $table->char('payload_hash', 64);
            $table->dateTime('attempted_at');
            $table->unique(['import_row_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_row_attempts');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
