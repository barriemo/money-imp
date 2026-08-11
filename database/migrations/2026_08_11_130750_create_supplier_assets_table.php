<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('supplier_profile_id')
                ->constrained('supplier_profiles')
                ->cascadeOnDelete();

            $table->string('asset_type');

            $table->string('asset_key');

            $table->string('name');

            $table->foreignUuid('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->string('purpose')
                ->nullable();

            $table->boolean('billable')
                ->default(false);

            $table->boolean('active')
                ->default(true);

            $table->decimal('observed_cost', 14, 2)
                ->default(0);

            $table->decimal('expected_charge', 14, 2)
                ->nullable();

            $table->date('first_seen_at')
                ->nullable();

            $table->date('last_seen_at')
                ->nullable();

            $table->date('renewal_date')
                ->nullable();

            $table->unsignedTinyInteger('confidence')
                ->default(0);

            $table->text('notes')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'supplier_profile_id',
                'asset_type',
                'asset_key',
            ], 'supplier_asset_unique');

            $table->index([
                'asset_type',
                'active',
                'billable',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_assets');
    }
};
