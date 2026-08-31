<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('bank_transaction_id');
            $table->uuid('accounting_bill_id');

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

            $table->foreign('accounting_bill_id')
                ->references('id')
                ->on('accounting_bills')
                ->cascadeOnDelete();

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            /*
             * Multiple allocation records are intentional.
             *
             * A single supplier payment may be allocated in stages:
             * approved £60 against a bill, followed by a £40 suggestion
             * for the remaining payment. Allocation history must never be
             * overwritten merely because the same transaction/bill pair
             * appears again.
             */
            $table->index(
                ['bank_transaction_id', 'accounting_bill_id', 'status'],
                'supplier_payment_allocations_lookup'
            );

            $table->index(['status', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
