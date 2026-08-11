<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('supplier_name');

            $table->string('supplier_key')
                ->unique();

            $table->string('category')
                ->nullable();

            $table->foreignUuid('default_client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->boolean('recoverable')
                ->default(false);

            $table->boolean('active')
                ->default(true);

            $table->decimal('expected_monthly_cost', 14, 2)
                ->nullable();

            $table->decimal('expected_annual_cost', 14, 2)
                ->nullable();

            $table->date('last_reviewed_at')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'category',
                'active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_profiles'
        );
    }
};
