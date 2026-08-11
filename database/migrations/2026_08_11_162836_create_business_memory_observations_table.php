<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_memory_observations',
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
                    'business_memory_entry_id'
                )
                    ->constrained(
                        'business_memory_entries'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'observation_type'
                );

                $table->text(
                    'statement'
                );

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->boolean(
                    'verified'
                )->default(false);

                $table->string(
                    'source'
                )->default('rule');

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'business_memory_id',
                    'observation_type',
                ]);

                $table->index([
                    'business_memory_entry_id',
                    'observation_type',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_memory_observations'
        );
    }
};
