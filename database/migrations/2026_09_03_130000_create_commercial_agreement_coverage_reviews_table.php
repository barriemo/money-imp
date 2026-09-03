<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'commercial_agreement_coverage_reviews',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                /*
                 * Coverage belongs to the canonical service universe.
                 *
                 * client_id is duplicated deliberately for audit/query
                 * convenience but must be derived from ClientService.
                 */
                $table->uuid('client_id');

                $table->uuid(
                    'client_service_id'
                );

                /*
                 * Coverage history is append-only.
                 *
                 * A later review supersedes the previous review without
                 * changing the historical row.
                 */
                $table->uuid(
                    'supersedes_commercial_agreement_coverage_review_id'
                )->nullable();

                /*
                 * Terminal:
                 *   confirmed_terms
                 *   no_current_contract
                 *
                 * Non-terminal:
                 *   needs_more_evidence
                 */
                $table->enum(
                    'outcome',
                    [
                        'confirmed_terms',
                        'no_current_contract',
                        'needs_more_evidence',
                    ]
                );

                /*
                 * Required only for confirmed_terms.
                 *
                 * The referenced agreement must be the current
                 * human-confirmed agreement assertion for this service
                 * as at effective_from.
                 */
                $table->uuid(
                    'commercial_agreement_id'
                )->nullable();

                /*
                 * Coverage is as-of aware.
                 *
                 * A future review must not hide today's review.
                 */
                $table->date(
                    'effective_from'
                );

                $table->string('source');

                $table->string(
                    'source_reference'
                )->nullable();

                $table
                    ->foreignId('reviewed_by');

                $table->string(
                    'reviewed_by_name'
                );

                $table->timestamp(
                    'reviewed_at'
                );

                $table->text(
                    'reason'
                );

                /*
                 * Frozen evidence considered by the human.
                 */
                $table->json(
                    'evidence_snapshot'
                );

                $table->json(
                    'metadata'
                )->nullable();

                /*
                 * No updated_at by design.
                 */
                $table->timestamp(
                    'created_at'
                )->useCurrent();

                $table
                    ->foreign('client_id')
                    ->references('id')
                    ->on('clients')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'client_service_id'
                    )
                    ->references('id')
                    ->on('client_services')
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'supersedes_commercial_agreement_coverage_review_id'
                    )
                    ->references('id')
                    ->on(
                        'commercial_agreement_coverage_reviews'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'commercial_agreement_id'
                    )
                    ->references('id')
                    ->on('commercial_agreements')
                    ->restrictOnDelete();

                $table
                    ->foreign('reviewed_by')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                /*
                 * Prevent history branching.
                 */
                $table->unique(
                    'supersedes_commercial_agreement_coverage_review_id',
                    'commercial_agreement_coverage_single_successor'
                );

                $table->index(
                    [
                        'client_id',
                        'client_service_id',
                    ],
                    'commercial_agreement_coverage_service'
                );

                $table->index(
                    [
                        'outcome',
                        'effective_from',
                    ],
                    'commercial_agreement_coverage_outcome'
                );
            }
        );

        /*
         * Coverage reviews are contractual audit assertions.
         * Protect them below Eloquent as well as inside Eloquent.
         */
        DB::statement(
            <<<'SQL'
CREATE TRIGGER commercial_agreement_coverage_reviews_immutable_update
BEFORE UPDATE ON commercial_agreement_coverage_reviews
BEGIN
    SELECT RAISE(
        ABORT,
        'Commercial agreement coverage reviews are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER commercial_agreement_coverage_reviews_immutable_delete
BEFORE DELETE ON commercial_agreement_coverage_reviews
BEGIN
    SELECT RAISE(
        ABORT,
        'Commercial agreement coverage reviews are immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        /*
         * Do not destroy real human coverage decisions during rollback.
         */
        if (
            DB::table(
                'commercial_agreement_coverage_reviews'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back commercial agreement coverage schema after coverage truth has been recorded.'
            );
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS commercial_agreement_coverage_reviews_immutable_delete'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS commercial_agreement_coverage_reviews_immutable_update'
        );

        Schema::dropIfExists(
            'commercial_agreement_coverage_reviews'
        );
    }
};
