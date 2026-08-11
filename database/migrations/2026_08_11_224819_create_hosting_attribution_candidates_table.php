<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hosting_attribution_candidates',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'client_id'
                )
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->foreignUuid(
                    'supplier_asset_id'
                )
                    ->nullable()
                    ->constrained('supplier_assets')
                    ->nullOnDelete();

                $table->foreignUuid(
                    'managed_service_id'
                )
                    ->nullable()
                    ->constrained('managed_services')
                    ->nullOnDelete();

                $table->uuid(
                    'accounting_invoice_item_id'
                )->nullable();

                $table->string(
                    'relationship_type'
                )->default('hosts');

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->string(
                    'status'
                )->default('candidate');

                $table->string(
                    'source'
                )->default('hosting_attribution');

                $table->text(
                    'reason'
                )->nullable();

                $table->json(
                    'evidence'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'status',
                ]);

                $table->index([
                    'supplier_asset_id',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'hosting_attribution_candidates'
        );
    }
};
