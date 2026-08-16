<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_experiences', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid(
                'source_investigation_case_id'
            )
                ->constrained('investigation_cases')
                ->cascadeOnDelete();

            $table->string('fingerprint')
                ->unique();

            $table->string('type');

            $table->string('subject_type')
                ->nullable();

            $table->string('subject_id')
                ->nullable();

            $table->string('subject_name')
                ->nullable();

            $table->string('title');

            $table->text('summary')
                ->nullable();

            $table->text('outcome')
                ->nullable();

            $table->unsignedTinyInteger('confidence')
                ->default(0);

            $table->unsignedTinyInteger('importance')
                ->default(50);

            $table->text('hypothesis')
                ->nullable();

            $table->json('lessons')
                ->nullable();

            $table->json('evidence_summary')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamp('experienced_at');

            $table->timestamps();

            $table->unique(
                'source_investigation_case_id'
            );

            $table->index([
                'subject_type',
                'subject_id',
            ]);

            $table->index([
                'type',
                'experienced_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_experiences'
        );
    }
};
