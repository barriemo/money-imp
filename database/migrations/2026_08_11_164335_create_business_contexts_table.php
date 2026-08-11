<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_contexts',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'business_memory_id'
                )
                    ->constrained(
                        'business_memories'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'context_type'
                );

                $table->string(
                    'key'
                );

                $table->text(
                    'value'
                );

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(100);

                $table->boolean(
                    'verified'
                )->default(false);

                $table->string(
                    'source'
                )->default('manual');

                $table->dateTime(
                    'effective_from'
                )->nullable();

                $table->dateTime(
                    'effective_until'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'business_memory_id',
                    'context_type',
                    'key',
                ], 'business_context_unique');

                $table->index([
                    'business_memory_id',
                    'context_type',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_contexts'
        );
    }
};
