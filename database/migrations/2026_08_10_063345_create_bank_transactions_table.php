<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('bank_account_id');
            $table->uuid('client_id')->nullable();

            $table->date('transaction_date');

            // Signed amount:
            // positive = money in
            // negative = money out
            $table->decimal('amount', 15, 2);

            $table->string('currency', 3)->default('GBP');

            $table->text('description')->nullable();
            $table->string('reference')->nullable();

            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account')->nullable();

            $table->string('transaction_type')->nullable();

            /*
             * imported
             * unmatched
             * suggested
             * client_matched
             * partially_allocated
             * reconciled
             * ignored
             */
            $table->string('match_status')->default('imported');

            $table->decimal('match_confidence', 5, 2)->nullable();

            $table->uuid('matched_by')->nullable();
            $table->timestamp('matched_at')->nullable();

            /*
             * freeagent
             * csv
             * pdf
             * manual
             * api
             */
            $table->string('source_type')->default('manual');
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();

            // Deterministic duplicate protection.
            $table->string('transaction_hash', 64);

            // Original imported representation where useful.
            $table->json('raw_payload')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->cascadeOnDelete();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();

            $table->foreign('matched_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['bank_account_id', 'transaction_hash'],
                'bank_transactions_import_unique'
            );

            $table->index(['transaction_date', 'amount']);
            $table->index(['client_id', 'transaction_date']);
            $table->index(['match_status', 'transaction_date']);
            $table->index(['counterparty_name', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
