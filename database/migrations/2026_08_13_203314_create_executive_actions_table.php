<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('fingerprint')->unique();

            $table->uuid('client_id')->nullable();

            $table->string('client')->nullable();

            $table->string('type');

            $table->string('title');

            $table->text('description');

            $table->text('recommended_action');

            $table->decimal(
                'estimated_financial_impact',
                15,
                2
            )->nullable();

            $table->unsignedInteger(
                'estimated_effort_minutes'
            )->nullable();

            $table->unsignedTinyInteger(
                'confidence'
            );

            $table->unsignedTinyInteger(
                'urgency'
            );

            $table->unsignedTinyInteger(
                'score'
            );

            $table->string('status')
                ->default('pending');

            $table->timestamp('due_at')
                ->nullable();

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamp('verified_at')
                ->nullable();

            $table->text('outcome')
                ->nullable();

            $table->decimal(
                'financial_result',
                15,
                2
            )->nullable();

            $table->json('evidence')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();

            $table->index([
                'status',
                'score',
            ]);

            $table->index([
                'client_id',
                'status',
            ]);

            $table->index([
                'due_at',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_actions');
    }
};
