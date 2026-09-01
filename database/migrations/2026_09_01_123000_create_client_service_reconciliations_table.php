<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'client_service_reconciliations',
            function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->uuid('client_id');

                /*
                 * The candidate fingerprint identifies the inferred
                 * commercial service identity.
                 */
                $table->string(
                    'candidate_fingerprint',
                    64
                );

                /*
                 * The evidence fingerprint identifies the exact
                 * invoice-item evidence set reviewed by the human.
                 *
                 * The candidate identity may remain unchanged while
                 * new invoice evidence appears later.
                 */
                $table->string(
                    'evidence_fingerprint',
                    64
                );

                $table->string('service_type');

                $table->string(
                    'service_hint'
                )->nullable();

                /*
                 * Current decisions:
                 *
                 * rejected
                 * deferred
                 *
                 * Reserved for the canonical promotion slice:
                 *
                 * confirmed
                 * merged
                 */
                $table->string('decision');

                $table->uuid(
                    'client_service_id'
                )->nullable();

                /*
                 * Users use bigint IDs in this application.
                 */
                $table
                    ->foreignId('reviewed_by')
                    ->nullable();

                $table
                    ->timestamp('reviewed_at');

                $table
                    ->text('reason')
                    ->nullable();

                $table->json(
                    'candidate_snapshot'
                );

                $table->timestamps();

                $table
                    ->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->cascadeOnDelete();

                $table
                    ->foreign('client_service_id')
                    ->references('id')
                    ->on('client_services')
                    ->nullOnDelete();

                $table
                    ->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    [
                        'client_id',
                        'candidate_fingerprint',
                        'evidence_fingerprint',
                    ],
                    'client_service_reconciliation_evidence'
                );

                $table->index(
                    [
                        'decision',
                        'reviewed_at',
                    ],
                    'client_service_reconciliation_review'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'client_service_reconciliations'
        );
    }
};
