<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('type');

            $table->string('subject_type')->nullable();

            $table->string('subject_id')->nullable();

            $table->string('subject_name')->nullable();

            $table->string('title');

            $table->text('question')->nullable();

            $table->string('status')
                ->default('open');

            $table->unsignedTinyInteger('confidence')
                ->default(0);

            $table->text('current_hypothesis')
                ->nullable();

            $table->text('verdict')
                ->nullable();

            $table->timestamp('opened_at');

            $table->timestamp('closed_at')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'subject_type',
                'subject_id',
            ]);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'investigation_cases'
        );
    }
};
