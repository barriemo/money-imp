<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_facilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('provider');

            $table->string('name');

            $table->string('facility_type');

            $table->string('currency')
                ->default('GBP');

            $table->decimal(
                'credit_limit',
                14,
                2
            )->nullable();

            $table->decimal(
                'reported_balance',
                14,
                2
            )->nullable();

            $table->timestamp(
                'reported_balance_at'
            )->nullable();

            $table->decimal(
                'minimum_payment',
                14,
                2
            )->nullable();

            $table->date(
                'payment_due_at'
            )->nullable();

            $table->boolean(
                'verified'
            )->default(false);

            $table->unsignedTinyInteger(
                'confidence'
            )->default(0);

            $table->string('status')
                ->default('active');

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'provider',
                'status',
            ]);

            $table->index([
                'payment_due_at',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'credit_facilities'
        );
    }
};
