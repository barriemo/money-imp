<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_decision_outcomes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('decision_type');

            $table->uuid('client_id')->nullable();

            $table->string('client')->nullable();

            $table->text('action');

            $table->text('reason')->nullable();

            $table->unsignedTinyInteger('priority')->default(0);

            $table->decimal('value', 12, 2)->nullable();

            $table->string('status')->default('pending');

            $table->text('outcome')->nullable();

            $table->decimal('financial_result', 12, 2)->nullable();

            $table->timestamp('decided_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'decision_type',
            ]);

            $table->index([
                'client_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_decision_outcomes');
    }
};
