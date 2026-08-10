<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('accounting_invoice_id')->unique();
            $table->string('status')->default('pending');

            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('accounting_invoice_id')
                ->references('id')
                ->on('accounting_invoices')
                ->cascadeOnDelete();

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reviews');
    }
};
