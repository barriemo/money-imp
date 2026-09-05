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
            'contracted_capacity_assertions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignId(
                    'user_id'
                );

                $table->uuid(
                    'supersedes_contracted_capacity_assertion_id'
                )->nullable();

                /*
                 * confirmed
                 *   Explicit positive contracted working capacity.
                 *
                 * no_fixed_capacity
                 *   Explicit human confirmation that no fixed
                 *   contracted working-capacity denominator applies.
                 *
                 * Unknown is absence of a current assertion.
                 */
                $table->enum(
                    'capacity_status',
                    [
                        'confirmed',
                        'no_fixed_capacity',
                    ]
                );

                /*
                 * Exact integer minutes for the asserted period.
                 *
                 * Null only for no_fixed_capacity.
                 *
                 * Zero is never used as an unknown/no-capacity
                 * sentinel.
                 */
                $table->unsignedInteger(
                    'contracted_minutes'
                )->nullable();

                $table->enum(
                    'period_basis',
                    [
                        'daily',
                        'weekly',
                        'monthly',
                        'annual',
                    ]
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

                /*
                 * Append-only assertion ledger:
                 * intentionally no updated_at.
                 */
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
                        'supersedes_contracted_capacity_assertion_id'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'contracted_capacity_assertions'
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
                    'supersedes_contracted_capacity_assertion_id',
                    'contracted_capacity_single_successor'
                );

                $table->index(
                    [
                        'user_id',
                        'effective_from',
                    ],
                    'contracted_capacity_user_effective'
                );

                $table->index(
                    [
                        'capacity_status',
                        'effective_from',
                    ],
                    'contracted_capacity_status_effective'
                );
            }
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER contracted_capacity_assertions_validate_insert
BEFORE INSERT ON contracted_capacity_assertions
BEGIN
    SELECT CASE
        WHEN NEW.effective_to IS NOT NULL
         AND NEW.effective_to < NEW.effective_from
        THEN RAISE(
            ABORT,
            'Contracted capacity assertion has an invalid effective date range'
        )
    END;

    SELECT CASE
        WHEN NEW.capacity_status = 'confirmed'
         AND (
             NEW.contracted_minutes IS NULL
             OR NEW.contracted_minutes <= 0
             OR NEW.period_basis IS NULL
         )
        THEN RAISE(
            ABORT,
            'Confirmed contracted capacity requires positive minutes and a period basis'
        )
    END;

    SELECT CASE
        WHEN NEW.capacity_status = 'no_fixed_capacity'
         AND (
             NEW.contracted_minutes IS NOT NULL
             OR NEW.period_basis IS NOT NULL
         )
        THEN RAISE(
            ABORT,
            'No-fixed-capacity assertion must not carry contracted minutes or period basis'
        )
    END;

    SELECT CASE
        WHEN NEW.supersedes_contracted_capacity_assertion_id IS NOT NULL
         AND NOT EXISTS (
             SELECT 1
             FROM contracted_capacity_assertions predecessor
             WHERE predecessor.id =
                 NEW.supersedes_contracted_capacity_assertion_id
               AND predecessor.user_id = NEW.user_id
         )
        THEN RAISE(
            ABORT,
            'Contracted capacity assertion may supersede only an assertion for the same User'
        )
    END;
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER contracted_capacity_assertions_immutable_update
BEFORE UPDATE ON contracted_capacity_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Contracted capacity assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER contracted_capacity_assertions_immutable_delete
BEFORE DELETE ON contracted_capacity_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Contracted capacity assertions are immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'contracted_capacity_assertions'
            )
            && DB::table(
                'contracted_capacity_assertions'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back canonical contracted-capacity truth after assertions have been recorded.'
            );
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS contracted_capacity_assertions_validate_insert'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS contracted_capacity_assertions_immutable_update'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS contracted_capacity_assertions_immutable_delete'
        );

        Schema::dropIfExists(
            'contracted_capacity_assertions'
        );
    }
};
