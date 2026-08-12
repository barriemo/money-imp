<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'timeline_events',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string('subject_type');
                $table->uuid('subject_id');

                $table->string('event_type');

                $table->string('source');

                $table->string('field')->nullable();

                $table->json('before')->nullable();
                $table->json('after')->nullable();

                $table->unsignedTinyInteger('confidence_before')->nullable();
                $table->unsignedTinyInteger('confidence_after')->nullable();

                $table->text('summary');

                $table->json('metadata')->nullable();

                $table->timestamp('occurred_at');

                $table->timestamps();

                $table->index([
                    'subject_type',
                    'subject_id',
                    'occurred_at',
                ]);

                $table->index([
                    'event_type',
                    'occurred_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'timeline_events'
        );
    }
};
