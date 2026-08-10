<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('accounting_invoice_id');
            $table->uuid('client_service_id')->nullable();

            $table->text('description');

            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);

            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('accounting_invoice_id')
                ->references('id')
                ->on('accounting_invoices')
                ->cascadeOnDelete();

            $table->foreign('client_service_id')
                ->references('id')
                ->on('client_services')
                ->nullOnDelete();

            $table->index('client_service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_invoice_items');
    }
};
