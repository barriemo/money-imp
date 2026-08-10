<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transaction_explanations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('bank_transaction_id');

            $table->string('type')->nullable();
            $table->string('category')->nullable();

            $table->decimal('amount', 15, 2);

            $table->text('description')->nullable();

            $table->string('status')->default('observed');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->cascadeOnDelete();

            $table->index(['bank_transaction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_explanations');
    }
};
