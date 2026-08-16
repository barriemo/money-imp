<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid(
                'investigation_case_id'
            )
                ->constrained('investigation_cases')
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('actor_type');

            $table->text('description');

            $table->json('payload')
                ->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index([
                'investigation_case_id',
                'occurred_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'investigation_events'
        );
    }
};
