<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'infrastructure_billing_allocations',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'supplier_asset_id'
                )
                    ->constrained(
                        'supplier_assets'
                    )
                    ->cascadeOnDelete();

                $table->foreignUuid(
                    'accounting_invoice_item_id'
                )
                    ->constrained(
                        'accounting_invoice_items'
                    )
                    ->cascadeOnDelete();

                $table->decimal(
                    'allocated_amount',
                    14,
                    2
                );

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(100);

                $table->string('source')
                    ->default('automatic');

                $table->boolean('verified')
                    ->default(false);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'supplier_asset_id',
                    'accounting_invoice_item_id',
                ], 'infra_billing_allocation_unique');

                $table->index(
                    'accounting_invoice_item_id'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'infrastructure_billing_allocations'
        );
    }
};
