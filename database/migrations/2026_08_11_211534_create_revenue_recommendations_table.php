<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'revenue_recommendations',
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

                $table->string(
                    'type'
                );

                $table->string(
                    'status'
                )->default('open');

                $table->unsignedTinyInteger(
                    'priority'
                )->default(50);

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(50);

                $table->string(
                    'title'
                );

                $table->text(
                    'description'
                )->nullable();

                $table->text(
                    'recommended_action'
                )->nullable();

                $table->decimal(
                    'estimated_monthly_value',
                    12,
                    2
                )->default(0);

                $table->decimal(
                    'estimated_annual_value',
                    12,
                    2
                )->default(0);

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'status',
                ]);

                $table->index([
                    'type',
                    'status',
                ]);

                $table->unique([
                    'client_id',
                    'supplier_asset_id',
                    'type',
                    'status',
                ], 'revenue_recommendation_open_asset_unique');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'revenue_recommendations'
        );
    }
};
