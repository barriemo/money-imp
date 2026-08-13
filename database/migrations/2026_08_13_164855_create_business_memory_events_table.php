<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_memory_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('client_id')->nullable();

            $table->string('client')->nullable();

            $table->string('type');

            $table->string('source_type')->nullable();

            $table->uuid('source_id')->nullable();

            $table->string('title');

            $table->text('description');

            $table->decimal('value', 12, 2)->nullable();

            $table->unsignedTinyInteger('confidence')->default(100);

            $table->timestamp('occurred_at');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'client_id',
                'occurred_at',
            ]);

            $table->index([
                'type',
                'occurred_at',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_memory_events');
    }
};
