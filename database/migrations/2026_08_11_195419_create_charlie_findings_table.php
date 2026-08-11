<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'charlie_findings',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'charlie_review_id'
                )
                    ->constrained(
                        'charlie_reviews'
                    )
                    ->cascadeOnDelete();

                $table->foreignUuid(
                    'client_id'
                )
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->string(
                    'category'
                );

                $table->string(
                    'severity'
                );

                $table->string(
                    'title'
                );

                $table->text(
                    'description'
                )->nullable();

                $table->text(
                    'suggested_action'
                )->nullable();

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->decimal(
                    'estimated_monthly_value',
                    12,
                    2
                )->nullable();

                $table->unsignedTinyInteger(
                    'priority_score'
                )->default(50);

                $table->string(
                    'status'
                )->default('open');

                $table->string(
                    'source'
                )->nullable();

                $table->string(
                    'source_reference'
                )->nullable();

                $table->json(
                    'evidence'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'status',
                    'priority_score',
                ]);

                $table->index([
                    'category',
                    'severity',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'charlie_findings'
        );
    }
};
