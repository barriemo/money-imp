<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_memory_entries',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'business_memory_id'
                )
                    ->constrained(
                        'business_memories'
                    )
                    ->cascadeOnDelete();

                $table->string('entry_type');

                $table->dateTime(
                    'occurred_at'
                );

                $table->longText(
                    'content'
                );

                $table->text('summary')
                    ->nullable();

                $table->string('source')
                    ->default('manual');

                $table->string(
                    'source_reference'
                )->nullable();

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(100);

                $table->boolean(
                    'verified'
                )->default(false);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'business_memory_id',
                    'occurred_at',
                ]);

                $table->index([
                    'entry_type',
                    'occurred_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_memory_entries'
        );
    }
};
