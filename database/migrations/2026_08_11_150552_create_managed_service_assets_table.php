<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'managed_service_assets',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid(
                    'managed_service_id'
                )
                    ->constrained(
                        'managed_services'
                    )
                    ->cascadeOnDelete();

                $table->foreignUuid(
                    'supplier_asset_id'
                )
                    ->constrained(
                        'supplier_assets'
                    )
                    ->cascadeOnDelete();

                $table->string('role')
                    ->default('dependency');

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(100);

                $table->boolean('verified')
                    ->default(false);

                $table->string('source')
                    ->default('manual');

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'managed_service_id',
                    'supplier_asset_id',
                ], 'managed_service_asset_unique');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'managed_service_assets'
        );
    }
};
