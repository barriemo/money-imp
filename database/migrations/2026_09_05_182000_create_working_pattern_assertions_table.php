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
            'working_pattern_assertions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignId(
                    'user_id'
                );

                $table->uuid(
                    'supersedes_working_pattern_assertion_id'
                )->nullable();

                /*
                 * confirmed
                 *   Explicit fixed recurring weekly pattern.
                 *
                 * no_fixed_pattern
                 *   Explicit human confirmation that no fixed
                 *   recurring weekly distribution applies.
                 *
                 * Unknown is absence of a current assertion.
                 */
                $table->enum(
                    'pattern_status',
                    [
                        'confirmed',
                        'no_fixed_pattern',
                    ]
                );

                /*
                 * Weekly is deliberately the only V1 fixed-pattern
                 * recurrence.
                 *
                 * A different recurring structure must not be forced
                 * into this representation.
                 */
                $table->enum(
                    'pattern_basis',
                    [
                        'weekly',
                    ]
                )->nullable();

                /*
                 * Confirmed patterns explicitly state every day.
                 *
                 * 0 means a known recurring non-working day.
                 *
                 * Null is reserved for no_fixed_pattern.
                 */
                $table->unsignedSmallInteger(
                    'monday_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'tuesday_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'wednesday_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'thursday_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'friday_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'saturday_minutes'
                )->nullable();

                $table->unsignedSmallInteger(
                    'sunday_minutes'
                )->nullable();

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
                        'supersedes_working_pattern_assertion_id'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'working_pattern_assertions'
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
                    'supersedes_working_pattern_assertion_id',
                    'working_pattern_single_successor'
                );

                $table->index(
                    [
                        'user_id',
                        'effective_from',
                    ],
                    'working_pattern_user_effective'
                );

                $table->index(
                    [
                        'pattern_status',
                        'effective_from',
                    ],
                    'working_pattern_status_effective'
                );
            }
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER working_pattern_assertions_validate_insert
BEFORE INSERT ON working_pattern_assertions
BEGIN
    SELECT CASE
        WHEN NEW.effective_to IS NOT NULL
         AND NEW.effective_to < NEW.effective_from
        THEN RAISE(
            ABORT,
            'Working pattern assertion has an invalid effective date range'
        )
    END;

    SELECT CASE
        WHEN NEW.pattern_status = 'confirmed'
         AND (
             NEW.pattern_basis IS NULL
             OR NEW.pattern_basis != 'weekly'
             OR NEW.monday_minutes IS NULL
             OR NEW.tuesday_minutes IS NULL
             OR NEW.wednesday_minutes IS NULL
             OR NEW.thursday_minutes IS NULL
             OR NEW.friday_minutes IS NULL
             OR NEW.saturday_minutes IS NULL
             OR NEW.sunday_minutes IS NULL
         )
        THEN RAISE(
            ABORT,
            'Confirmed working pattern requires an explicit weekly value for every day'
        )
    END;

    SELECT CASE
        WHEN NEW.pattern_status = 'confirmed'
         AND (
             NEW.monday_minutes < 0
             OR NEW.monday_minutes > 1440
             OR NEW.tuesday_minutes < 0
             OR NEW.tuesday_minutes > 1440
             OR NEW.wednesday_minutes < 0
             OR NEW.wednesday_minutes > 1440
             OR NEW.thursday_minutes < 0
             OR NEW.thursday_minutes > 1440
             OR NEW.friday_minutes < 0
             OR NEW.friday_minutes > 1440
             OR NEW.saturday_minutes < 0
             OR NEW.saturday_minutes > 1440
             OR NEW.sunday_minutes < 0
             OR NEW.sunday_minutes > 1440
         )
        THEN RAISE(
            ABORT,
            'Working pattern day minutes must be between zero and 1440'
        )
    END;

    SELECT CASE
        WHEN NEW.pattern_status = 'confirmed'
         AND (
             NEW.monday_minutes
             + NEW.tuesday_minutes
             + NEW.wednesday_minutes
             + NEW.thursday_minutes
             + NEW.friday_minutes
             + NEW.saturday_minutes
             + NEW.sunday_minutes
         ) <= 0
        THEN RAISE(
            ABORT,
            'Confirmed working pattern requires positive scheduled minutes in the week'
        )
    END;

    SELECT CASE
        WHEN NEW.pattern_status = 'no_fixed_pattern'
         AND (
             NEW.pattern_basis IS NOT NULL
             OR NEW.monday_minutes IS NOT NULL
             OR NEW.tuesday_minutes IS NOT NULL
             OR NEW.wednesday_minutes IS NOT NULL
             OR NEW.thursday_minutes IS NOT NULL
             OR NEW.friday_minutes IS NOT NULL
             OR NEW.saturday_minutes IS NOT NULL
             OR NEW.sunday_minutes IS NOT NULL
         )
        THEN RAISE(
            ABORT,
            'No-fixed-pattern assertion must not carry recurring weekday minutes'
        )
    END;

    SELECT CASE
        WHEN NEW.supersedes_working_pattern_assertion_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1
             FROM working_pattern_assertions predecessor
             WHERE predecessor.id =
                 NEW.supersedes_working_pattern_assertion_id
               AND predecessor.user_id = NEW.user_id
         )
        THEN RAISE(
            ABORT,
            'Working pattern assertion may supersede only an assertion for the same User'
        )
    END;
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER working_pattern_assertions_immutable_update
BEFORE UPDATE ON working_pattern_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Working pattern assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER working_pattern_assertions_immutable_delete
BEFORE DELETE ON working_pattern_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Working pattern assertions are immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'working_pattern_assertions'
            )
            && DB::table(
                'working_pattern_assertions'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back canonical working-pattern truth after assertions have been recorded.'
            );
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS working_pattern_assertions_validate_insert'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS working_pattern_assertions_immutable_update'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS working_pattern_assertions_immutable_delete'
        );

        Schema::dropIfExists(
            'working_pattern_assertions'
        );
    }
};
