<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'charlie_reviews',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'client_id'
                )
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->dateTime(
                    'reviewed_at'
                );

                $table->unsignedInteger(
                    'finding_count'
                )->default(0);

                $table->unsignedInteger(
                    'high_priority_count'
                )->default(0);

                $table->string(
                    'status'
                )->default('complete');

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'reviewed_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'charlie_reviews'
        );
    }
};
