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
            'team_membership_assertions',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignId(
                    'user_id'
                );

                $table->uuid(
                    'supersedes_team_membership_assertion_id'
                )->nullable();

                $table->enum(
                    'membership_status',
                    [
                        'member',
                        'not_member',
                    ]
                );

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
                        'supersedes_team_membership_assertion_id'
                    )
                    ->references(
                        'id'
                    )
                    ->on(
                        'team_membership_assertions'
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
                    'supersedes_team_membership_assertion_id',
                    'team_membership_single_successor'
                );

                $table->index(
                    [
                        'user_id',
                        'effective_from',
                    ],
                    'team_membership_user_effective'
                );

                $table->index(
                    [
                        'membership_status',
                        'effective_from',
                    ],
                    'team_membership_status_effective'
                );
            }
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER team_membership_assertions_immutable_update
BEFORE UPDATE ON team_membership_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Team membership assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER team_membership_assertions_immutable_delete
BEFORE DELETE ON team_membership_assertions
BEGIN
    SELECT RAISE(
        ABORT,
        'Team membership assertions are immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'team_membership_assertions'
            )
            && DB::table(
                'team_membership_assertions'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back canonical team-membership truth after assertions have been recorded.'
            );
        }

        DB::statement(
            'DROP TRIGGER IF EXISTS team_membership_assertions_immutable_update'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS team_membership_assertions_immutable_delete'
        );

        Schema::dropIfExists(
            'team_membership_assertions'
        );
    }
};
