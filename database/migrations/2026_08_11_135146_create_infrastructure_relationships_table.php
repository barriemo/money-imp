<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'infrastructure_relationships',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid('from_asset_id')
                    ->constrained(
                        'supplier_assets'
                    )
                    ->cascadeOnDelete();

                $table->foreignUuid('to_asset_id')
                    ->constrained(
                        'supplier_assets'
                    )
                    ->cascadeOnDelete();

                $table->string(
                    'relationship'
                );

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(100);

                $table->string('source')
                    ->default('manual');

                $table->boolean('verified')
                    ->default(false);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'from_asset_id',
                    'to_asset_id',
                    'relationship',
                ], 'infrastructure_relationship_unique');

                $table->index([
                    'relationship',
                    'verified',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'infrastructure_relationships'
        );
    }
};
