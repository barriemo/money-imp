<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_belief_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'business_belief_id'
                )
                    ->constrained(
                        'business_beliefs'
                    )
                    ->cascadeOnDelete();

                $table->nullableUuidMorphs(
                    'evidence'
                );

                $table->string(
                    'relationship'
                )->default('supports');

                $table->unsignedTinyInteger(
                    'weight'
                )->default(50);

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->text(
                    'summary'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'business_belief_id',
                    'relationship',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_belief_evidence'
        );
    }
};
