<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'credit_statement_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid('credit_facility_id')
                    ->constrained('credit_facilities')
                    ->cascadeOnDelete();

                $table->uuid('import_batch_id')
                    ->nullable();

                $table->date('statement_from')
                    ->nullable();

                $table->date('statement_to')
                    ->nullable();

                $table->decimal(
                    'opening_balance',
                    14,
                    2
                )->nullable();

                $table->decimal(
                    'closing_balance',
                    14,
                    2
                );

                $table->decimal(
                    'minimum_payment',
                    14,
                    2
                )->nullable();

                $table->date('payment_due_at')
                    ->nullable();

                $table->decimal(
                    'credit_limit',
                    14,
                    2
                )->nullable();

                $table->string('source');

                $table->boolean('verified')
                    ->default(false);

                $table->unsignedTinyInteger('confidence')
                    ->default(0);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'credit_facility_id',
                    'statement_to',
                ]);

                $table->unique(
                    [
                        'credit_facility_id',
                        'import_batch_id',
                    ],
                    'credit_statement_import_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'credit_statement_evidence'
        );
    }
};
