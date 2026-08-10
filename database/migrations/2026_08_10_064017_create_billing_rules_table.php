<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('client_id');
            $table->uuid('client_service_id')->nullable();

            $table->string('name');
            $table->string('status')->default('active');

            $table->string('billing_type')->default('fixed');
            $table->string('frequency')->nullable();

            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('GBP');

            $table->decimal('markup_percent', 7, 4)->nullable();
            $table->decimal('target_margin_percent', 7, 4)->nullable();

            $table->unsignedTinyInteger('invoice_day')->nullable();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->boolean('auto_prepare')->default(false);
            $table->boolean('auto_send')->default(false);

            $table->text('invoice_description')->nullable();

            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnDelete();

            $table->foreign('client_service_id')
                ->references('id')
                ->on('client_services')
                ->nullOnDelete();

            $table->index(['client_id', 'status']);
            $table->index(['frequency', 'invoice_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_rules');
    }
};
