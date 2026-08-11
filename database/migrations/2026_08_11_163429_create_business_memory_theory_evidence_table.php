<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_memory_theory_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'business_memory_theory_id'
                )
                    ->constrained(
                        'business_memory_theories'
                    )
                    ->cascadeOnDelete();

                $table->foreignUuid(
                    'business_memory_observation_id'
                )
                    ->constrained(
                        'business_memory_observations'
                    )
                    ->cascadeOnDelete();

                $table->unsignedTinyInteger(
                    'weight'
                )->default(50);

                $table->string('relationship')
                    ->default('supports');

                $table->timestamps();

                $table->unique([
                    'business_memory_theory_id',
                    'business_memory_observation_id',
                ], 'business_memory_theory_evidence_unique');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_memory_theory_evidence'
        );
    }
};
