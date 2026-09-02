<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'composite_commercial_reviews',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                /*
                 * Durable identity of the exact source evidence
                 * reviewed by the human.
                 *
                 * Composite candidate fingerprints are useful for
                 * family/similarity grouping but are not sufficient
                 * evidence identity because several atomic source
                 * items may deliberately share one fingerprint.
                 */
                $table->uuid(
                    'accounting_invoice_item_id'
                );

                $table->uuid('client_id');

                $table->string(
                    'candidate_fingerprint',
                    64
                );

                /*
                 * Hash of the exact commercially material atomic
                 * source state reviewed by the human.
                 */
                $table->string(
                    'evidence_fingerprint',
                    64
                );

                /*
                 * Null for non-terminal review history.
                 *
                 * Terminal structural decisions use the literal
                 * value "terminal". Combined with source evidence
                 * identity below, this gives us a database-level
                 * guarantee that one exact evidence state cannot
                 * receive two conflicting terminal decisions.
                 */
                $table->string(
                    'terminal_marker',
                    16
                )->nullable();

                /*
                 * Stage-one structural decisions:
                 *
                 * bundled_service
                 * requires_allocation
                 * deferred
                 *
                 * These decisions interpret evidence shape only.
                 * They do not themselves create canonical service
                 * truth or monetary allocations.
                 */
                $table->string('decision');

                $table
                    ->foreignId('reviewed_by')
                    ->nullable();

                $table->timestamp(
                    'reviewed_at'
                );

                $table
                    ->text('reason')
                    ->nullable();

                /*
                 * Frozen evidence / classifier / assessment state
                 * seen by the human when the decision was made.
                 */
                $table->json(
                    'candidate_snapshot'
                );

                $table->timestamps();

                $table
                    ->foreign(
                        'accounting_invoice_item_id'
                    )
                    ->references('id')
                    ->on('accounting_invoice_items')
                    ->restrictOnDelete();

                $table
                    ->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->restrictOnDelete();

                $table
                    ->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                /*
                 * Review history remains append-only.
                 *
                 * Multiple deferred reviews are permitted because
                 * terminal_marker is null for those rows.
                 *
                 * For an exact source evidence state, however, only
                 * one terminal structural decision may exist.
                 */
                $table->unique(
                    [
                        'accounting_invoice_item_id',
                        'evidence_fingerprint',
                        'terminal_marker',
                    ],
                    'composite_review_terminal_evidence_unique'
                );

                $table->index(
                    [
                        'accounting_invoice_item_id',
                        'reviewed_at',
                    ],
                    'composite_review_evidence_history'
                );

                $table->index(
                    [
                        'client_id',
                        'candidate_fingerprint',
                    ],
                    'composite_review_candidate_family'
                );

                $table->index(
                    [
                        'decision',
                        'reviewed_at',
                    ],
                    'composite_review_decision'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'composite_commercial_reviews'
        );
    }
};
