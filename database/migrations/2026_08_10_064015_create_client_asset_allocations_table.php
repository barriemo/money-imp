<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_asset_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('provider_asset_id');
            $table->uuid('client_id');
            $table->uuid('client_service_id')->nullable();

            /*
             * rechargeable
             * included
             * complimentary
             * internal
             */
            $table->string('billing_treatment')->default('included');

            $table->decimal('client_charge', 15, 2)->nullable();
            $table->string('currency', 3)->default('GBP');

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('provider_asset_id')
                ->references('id')
                ->on('provider_assets')
                ->cascadeOnDelete();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnDelete();

            $table->foreign('client_service_id')
                ->references('id')
                ->on('client_services')
                ->nullOnDelete();

            $table->foreign('assigned_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['client_id', 'billing_treatment']);
            $table->index(['provider_asset_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_asset_allocations');
    }
};
