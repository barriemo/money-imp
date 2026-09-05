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
            'non_working_exception_coverage_assertions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignId(
                    'user_id'
                );

                $table->uuid(
                    'supersedes_non_working_exception_coverage_assertion_id'
                )->nullable();

                /*
                 * complete
                 *   The non-working-exception ledger has been explicitly
                 *   reviewed as complete for this exact User and exact
                 *   inclusive covered window.
                 *
                 * not_complete
                 *   The ledger is explicitly not complete for this exact
                 *   User and exact inclusive covered window.
                 *
                 * Unknown is represented by absence of a current assertion.
                 */
                $table->enum(
                    'coverage_status',
                    [
                        'complete',
                        'not_complete',
                    ]
                );

                /*
                 * Exact inclusive calendar window whose exception coverage
                 * has been reviewed.
                 *
                 * This is coverage truth only. It is not availability.
                 */
                $table->date(
                    'covered_from'
                );

                $table->date(
                    'covered_to'
                );

                /*
                 * Assertion-history effective dates are distinct from the
                 * calendar window whose completeness was reviewed.
                 */
                $table->date(
                    'effective_from'
                );

                $table->date(
                    'effective_to'
                )->nullable();

                $table->string(
                    'source'
                );

                $table->string(
                    'source_reference'
                )->nullable();

                $table->foreignId(
                    'reviewed_by'
                )->nullable();

                $table->string(
                    'reviewed_by_name'
                );

                $table->timestamp(
                    'reviewed_at'
                );

                $table->text(
                    'reason'
                );

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamp(
                    'created_at'
                )->useCurrent();

                $table
                    ->foreign(
                        'user_id',
                        'nw_exc_cov_user_fk'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'users'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'supersedes_non_working_exception_coverage_assertion_id',
                        'nw_exc_cov_supersedes_fk'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'non_working_exception_coverage_assertions'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'reviewed_by',
                        'nw_exc_cov_reviewer_fk'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'users'
                    )
                    ->restrictOnDelete();

                /*
                 * Coverage history must remain one linear chain.
                 */
                $table->unique(
                    'supersedes_non_working_exception_coverage_assertion_id',
                    'nw_exc_cov_single_successor'
                );

                $table->index(
                    [
                        'user_id',
                        'covered_from',
                        'covered_to',
                    ],
                    'nw_exc_cov_user_window'
                );

                $table->index(
                    [
                        'coverage_status',
                        'effective_from',
                    ],
                    'nw_exc_cov_status_effective'
                );
            }
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER non_working_exception_coverage_validate_insert
BEFORE INSERT ON non_working_exception_coverage_assertions
BEGIN
    SELECT CASE
        WHEN NEW.covered_to < NEW.covered_from
        THEN RAISE(
            ABORT,
            'Non-working exception coverage assertion has an invalid covered date range'
        )
    END;

    SELECT CASE
        WHEN NEW.effective_to IS NOT NULL
         AND NEW.effective_to < NEW.effective_from
        THEN RAISE(
            ABORT,
            'Non-working exception coverage assertion has an invalid effective date range'
        )
    END;

    SELECT CASE
        WHEN NEW.supersedes_non_working_exception_coverage_assertion_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1
             FROM non_working_exception_coverage_assertions predecessor
             WHERE predecessor.id =
                 NEW.supersedes_non_working_exception_coverage_assertion_id
               AND predecessor.user_id = NEW.user_id
         )
        THEN RAISE(
            ABORT,
            'Non-working exception coverage may supersede only coverage for the same User'
        )
    END;
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER non_working_exception_coverage_immutable_update
BEFORE UPDATE ON non_working_exception_coverage_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Non-working exception coverage assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER non_working_exception_coverage_immutable_delete
BEFORE DELETE ON non_working_exception_coverage_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Non-working exception coverage assertions are immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'non_working_exception_coverage_assertions'
            )
            && DB::table(
                'non_working_exception_coverage_assertions'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back canonical non-working-exception coverage truth after assertions have been recorded.'
            );
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS non_working_exception_coverage_validate_insert'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS non_working_exception_coverage_immutable_update'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS non_working_exception_coverage_immutable_delete'
        );

        Schema::dropIfExists(
            'non_working_exception_coverage_assertions'
        );
    }
};
