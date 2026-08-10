<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('external_connection_id');

            $table->nullableUuidMorphs('recordable');

            $table->string('resource_type');
            $table->string('external_id');

            $table->string('external_parent_id')->nullable();
            $table->string('external_reference')->nullable();

            $table->string('status')->nullable();

            $table->timestamp('external_created_at')->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->string('source_hash', 64)->nullable();

            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('external_connection_id')
                ->references('id')
                ->on('external_connections')
                ->cascadeOnDelete();

            $table->unique(
                ['external_connection_id', 'resource_type', 'external_id'],
                'external_records_source_unique'
            );

            $table->index(['resource_type', 'external_reference']);
            $table->index('source_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_records');
    }
};
