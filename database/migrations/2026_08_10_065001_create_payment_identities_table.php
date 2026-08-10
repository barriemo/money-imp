<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_identities', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('client_id');

            /*
             * reference
             * counterparty_name
             * bank_account
             * composite
             */
            $table->string('identity_type');

            // Original value for audit/display.
            $table->string('identity_value');

            // Normalised value used for matching.
            $table->string('normalized_value');

            $table->string('direction')->default('incoming');

            $table->unsignedInteger('successful_matches')->default(0);
            $table->unsignedInteger('rejected_matches')->default(0);

            $table->decimal('confidence', 5, 2)->default(0);

            $table->timestamp('last_matched_at')->nullable();

            $table->uuid('created_by')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['identity_type', 'normalized_value', 'direction'],
                'payment_identities_match_unique'
            );

            $table->index(['client_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_identities');
    }
};
