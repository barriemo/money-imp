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
            'non_working_exception_assertions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignId(
                    'user_id'
                );

                /*
                 * Stable identity of one logical exception.
                 *
                 * A user may have many independent exception chains.
                 */
                $table->uuid(
                    'exception_key'
                );

                $table->uuid(
                    'supersedes_non_working_exception_assertion_id'
                )->nullable();

                /*
                 * confirmed
                 *   Explicit non-working exception effect.
                 *
                 * cancelled
                 *   Explicit human confirmation that the exception
                 *   effect represented by this chain no longer applies
                 *   from this assertion's effective date.
                 *
                 * Unknown / no evidence is absence.
                 */
                $table->enum(
                    'exception_status',
                    [
                        'confirmed',
                        'cancelled',
                    ]
                );

                /*
                 * full_scheduled_day
                 *   Remove whatever scheduled working minutes would
                 *   otherwise apply on each date in the inclusive
                 *   exception window.
                 *
                 * fixed_minutes
                 *   Exact non-working minutes on one explicit date.
                 */
                $table->enum(
                    'effect_type',
                    [
                        'full_scheduled_day',
                        'fixed_minutes',
                    ]
                )->nullable();

                $table->date(
                    'starts_on'
                )->nullable();

                $table->date(
                    'ends_on'
                )->nullable();

                /*
                 * Used only by fixed_minutes.
                 *
                 * This is exception truth, not available capacity.
                 */
                $table->unsignedSmallInteger(
                    'non_working_minutes'
                )->nullable();

                /*
                 * Assertion-history effective dates are separate from
                 * the calendar dates on which the exception occurs.
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
                        'user_id'
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
                        'supersedes_non_working_exception_assertion_id'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'non_working_exception_assertions'
                    )
                    ->restrictOnDelete();

                $table
                    ->foreign(
                        'reviewed_by'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'users'
                    )
                    ->restrictOnDelete();

                $table->unique(
                    'supersedes_non_working_exception_assertion_id',
                    'non_working_exception_single_successor'
                );

                $table->index(
                    [
                        'user_id',
                        'exception_key',
                    ],
                    'non_working_exception_user_key'
                );

                $table->index(
                    [
                        'starts_on',
                        'ends_on',
                    ],
                    'non_working_exception_window'
                );

                $table->index(
                    [
                        'exception_status',
                        'effective_from',
                    ],
                    'non_working_exception_status_effective'
                );
            }
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER non_working_exception_assertions_validate_insert
BEFORE INSERT ON non_working_exception_assertions
BEGIN
    SELECT CASE
        WHEN NEW.effective_to IS NOT NULL
         AND NEW.effective_to < NEW.effective_from
        THEN RAISE(
            ABORT,
            'Non-working exception assertion has an invalid effective date range'
        )
    END;

    SELECT CASE
        WHEN NEW.exception_status = 'confirmed'
         AND (
             NEW.effect_type IS NULL
             OR NEW.starts_on IS NULL
             OR NEW.ends_on IS NULL
         )
        THEN RAISE(
            ABORT,
            'Confirmed non-working exception requires an effect and explicit occurrence window'
        )
    END;

    SELECT CASE
        WHEN NEW.exception_status = 'confirmed'
         AND NEW.ends_on < NEW.starts_on
        THEN RAISE(
            ABORT,
            'Non-working exception has an invalid occurrence date range'
        )
    END;

    SELECT CASE
        WHEN NEW.exception_status = 'confirmed'
         AND NEW.effect_type = 'full_scheduled_day'
         AND NEW.non_working_minutes IS NOT NULL
        THEN RAISE(
            ABORT,
            'Full-scheduled-day exception must not carry fixed non-working minutes'
        )
    END;

    SELECT CASE
        WHEN NEW.exception_status = 'confirmed'
         AND NEW.effect_type = 'fixed_minutes'
         AND (
             NEW.starts_on != NEW.ends_on
             OR NEW.non_working_minutes IS NULL
             OR NEW.non_working_minutes <= 0
             OR NEW.non_working_minutes > 1440
         )
        THEN RAISE(
            ABORT,
            'Fixed-minutes exception requires one date and positive minutes up to 1440'
        )
    END;

    SELECT CASE
        WHEN NEW.exception_status = 'cancelled'
         AND NEW.supersedes_non_working_exception_assertion_id IS NULL
        THEN RAISE(
            ABORT,
            'Cancelled non-working exception must supersede an existing assertion'
        )
    END;

    SELECT CASE
        WHEN NEW.exception_status = 'cancelled'
         AND (
             NEW.effect_type IS NOT NULL
             OR NEW.starts_on IS NOT NULL
             OR NEW.ends_on IS NOT NULL
             OR NEW.non_working_minutes IS NOT NULL
         )
        THEN RAISE(
            ABORT,
            'Cancelled non-working exception must not carry an active exception effect'
        )
    END;

    SELECT CASE
        WHEN NEW.supersedes_non_working_exception_assertion_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1
             FROM non_working_exception_assertions predecessor
             WHERE predecessor.id =
                 NEW.supersedes_non_working_exception_assertion_id
               AND predecessor.user_id = NEW.user_id
               AND predecessor.exception_key = NEW.exception_key
         )
        THEN RAISE(
            ABORT,
            'Non-working exception may supersede only the same exception for the same User'
        )
    END;
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER non_working_exception_assertions_immutable_update
BEFORE UPDATE ON non_working_exception_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Non-working exception assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER non_working_exception_assertions_immutable_delete
BEFORE DELETE ON non_working_exception_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Non-working exception assertions are immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'non_working_exception_assertions'
            )
            && DB::table(
                'non_working_exception_assertions'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back canonical non-working-exception truth after assertions have been recorded.'
            );
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS non_working_exception_assertions_validate_insert'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS non_working_exception_assertions_immutable_update'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS non_working_exception_assertions_immutable_delete'
        );

        Schema::dropIfExists(
            'non_working_exception_assertions'
        );
    }
};
