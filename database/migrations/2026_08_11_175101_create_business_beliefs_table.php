<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_beliefs',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->nullableUuidMorphs(
                    'subject'
                );

                $table->string(
                    'belief_type'
                );

                $table->string(
                    'key'
                );

                $table->text(
                    'value'
                )->nullable();

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->string(
                    'status'
                )->default('active');

                $table->boolean(
                    'verified'
                )->default(false);

                $table->string(
                    'source'
                )->default('derived');

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'subject_type',
                    'subject_id',
                    'belief_type',
                    'key',
                ], 'business_belief_subject_unique');

                $table->index([
                    'belief_type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_beliefs'
        );
    }
};
