<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_failures', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('sync_run_id');
            $table->uuid('external_record_id')->nullable();

            $table->string('resource_type');
            $table->string('external_id')->nullable();

            $table->string('failure_type')->default('sync_error');
            $table->text('message');

            $table->json('context')->nullable();
            $table->json('payload')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();

            $table->timestamps();

            $table->foreign('sync_run_id')
                ->references('id')
                ->on('sync_runs')
                ->cascadeOnDelete();

            $table->foreign('external_record_id')
                ->references('id')
                ->on('external_records')
                ->nullOnDelete();

            $table->foreign('resolved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['sync_run_id', 'failure_type']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_failures');
    }
};
