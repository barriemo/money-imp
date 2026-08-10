<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('bank_transaction_id');
            $table->uuid('accounting_invoice_id');

            $table->decimal('amount', 15, 2);

            /*
             * suggested
             * approved
             * rejected
             * imported
             */
            $table->string('status')->default('suggested');

            $table->decimal('confidence', 5, 2)->nullable();

            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->string('match_method')->nullable();
            $table->text('reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->cascadeOnDelete();

            $table->foreign('accounting_invoice_id')
                ->references('id')
                ->on('accounting_invoices')
                ->cascadeOnDelete();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['bank_transaction_id', 'accounting_invoice_id'],
                'payment_allocations_invoice_unique'
            );

            $table->index(['status', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
