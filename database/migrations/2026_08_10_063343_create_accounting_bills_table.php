<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('supplier_id')->nullable();

            $table->string('bill_number')->nullable();
            $table->string('status')->default('draft');

            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();

            $table->string('currency', 3)->default('GBP');

            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->default(0);

            $table->text('notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();

            $table->index(['supplier_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index('bill_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bills');
    }
};
