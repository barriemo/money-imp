<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'revenue_recommendation_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'revenue_recommendation_id'
                )
                    ->constrained(
                        'revenue_recommendations'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'type'
                );

                $table->string(
                    'reference'
                )->nullable();

                $table->text(
                    'summary'
                );

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'revenue_recommendation_id',
                    'type',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'revenue_recommendation_evidence'
        );
    }
};
