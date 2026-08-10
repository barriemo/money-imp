<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_bill_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('accounting_bill_id');

            $table->text('description');

            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_cost', 15, 2)->default(0);

            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('accounting_bill_id')
                ->references('id')
                ->on('accounting_bills')
                ->cascadeOnDelete();

            $table->index('accounting_bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bill_items');
    }
};
