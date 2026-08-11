<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'attribution_candidates',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string(
                    'fingerprint',
                    64
                )->unique();

                $table->string(
                    'subject_type'
                );

                $table->uuid(
                    'subject_id'
                );

                $table->string(
                    'relationship_type'
                );

                $table->string(
                    'target_type'
                )->nullable();

                $table->uuid(
                    'target_id'
                )->nullable();

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->string(
                    'status'
                )->default('candidate');

                $table->string(
                    'source'
                );

                $table->text(
                    'reason'
                )->nullable();

                $table->json(
                    'evidence'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'subject_type',
                    'subject_id',
                    'status',
                ]);

                $table->index([
                    'target_type',
                    'target_id',
                    'status',
                ]);

                $table->index([
                    'relationship_type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'attribution_candidates'
        );
    }
};
