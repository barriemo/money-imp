<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'managed_service_component_knowledge',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'managed_service_id'
                )
                    ->constrained(
                        'managed_services'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'component_type'
                );

                $table->string(
                    'state'
                )->default('known_unverified');

                $table->text(
                    'value'
                )->nullable();

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->boolean(
                    'verified'
                )->default(false);

                $table->string(
                    'source'
                )->default('manual');

                $table->string(
                    'source_reference'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'managed_service_id',
                    'component_type',
                ], 'managed_service_component_knowledge_unique');

                $table->index([
                    'managed_service_id',
                    'state',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'managed_service_component_knowledge'
        );
    }
};
