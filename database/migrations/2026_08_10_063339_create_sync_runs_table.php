<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('external_connection_id')->nullable();

            $table->string('resource_type');
            $table->string('direction')->default('inbound');
            $table->string('status')->default('pending');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->unsignedInteger('records_failed')->default(0);

            $table->string('cursor')->nullable();

            $table->text('error_message')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('external_connection_id')
                ->references('id')
                ->on('external_connections')
                ->nullOnDelete();

            $table->index(['status', 'started_at']);
            $table->index(['external_connection_id', 'resource_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
