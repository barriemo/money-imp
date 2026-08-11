<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'managed_services',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid('client_id')
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->string('service_type');

                $table->string('name');

                $table->string('status')
                    ->default('active');

                $table->boolean('billable')
                    ->default(true);

                $table->decimal(
                    'expected_monthly_revenue',
                    14,
                    2
                )->nullable();

                $table->unsignedTinyInteger(
                    'confidence'
                )->default(100);

                $table->string('source')
                    ->default('manual');

                $table->json('metadata')
                    ->nullable();

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'service_type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'managed_services'
        );
    }
};
