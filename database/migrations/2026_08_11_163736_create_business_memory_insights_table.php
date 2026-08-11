<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_memory_insights',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'business_memory_id'
                )
                    ->constrained(
                        'business_memories'
                    )
                    ->cascadeOnDelete();

                $table->foreignUuid(
                    'business_memory_theory_id'
                )
                    ->nullable()
                    ->constrained(
                        'business_memory_theories'
                    )
                    ->nullOnDelete();

                $table->foreignUuid(
                    'business_memory_observation_id'
                )
                    ->nullable()
                    ->constrained(
                        'business_memory_observations'
                    )
                    ->nullOnDelete();

                $table->string(
                    'insight_type'
                );

                $table->string(
                    'title'
                );

                $table->text(
                    'summary'
                );

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->unsignedTinyInteger(
                    'priority'
                )->default(50);

                $table->string(
                    'status'
                )->default('open');

                $table->string(
                    'source'
                )->default('rule');

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'business_memory_id',
                    'status',
                    'priority',
                ]);

                $table->index([
                    'insight_type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_memory_insights'
        );
    }
};
