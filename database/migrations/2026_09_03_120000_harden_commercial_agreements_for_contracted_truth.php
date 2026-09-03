<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertLegacyTablesAreEmpty();

        DB::statement(
            'DROP TRIGGER IF EXISTS commercial_agreement_evidence_immutable_delete'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS commercial_agreement_evidence_immutable_update'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS commercial_agreements_immutable_delete'
        );

        DB::statement(
            'DROP TRIGGER IF EXISTS commercial_agreements_immutable_update'
        );

        Schema::dropIfExists(
            'commercial_agreement_evidence'
        );

        Schema::dropIfExists(
            'commercial_agreements'
        );

        Schema::create(
            'commercial_agreements',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                /*
                 * Contract truth belongs to an already-canonical
                 * ClientService. The client is duplicated deliberately
                 * for audit/query convenience, but the assertion service
                 * derives it from the canonical service.
                 */
                $table->uuid('client_id');

                $table->uuid(
                    'client_service_id'
                );

                /*
                 * Agreement history is append-only.
                 *
                 * A changed price, cadence or termination creates a new
                 * assertion pointing at the assertion it supersedes.
                 *
                 * The predecessor is never silently edited.
                 */
                $table->uuid(
                    'supersedes_commercial_agreement_id'
                )->nullable();

                /*
                 * Persisted inference candidates are forbidden.
                 *
                 * confirmed  = explicit human-confirmed terms
                 * terminated = explicit human-confirmed termination
                 */
                $table->enum(
                    'status',
                    [
                        'confirmed',
                        'terminated',
                    ]
                );

                $table->enum(
                    'cadence',
                    [
                        'monthly',
                        'quarterly',
                        'annual',
                        'one_off',
                    ]
                )->nullable();

                /*
                 * Exact agreed source amount.
                 *
                 * Pence is authoritative so contractual money is never
                 * created through floating-point arithmetic.
                 */
                $table->bigInteger(
                    'contracted_amount_pence'
                )->nullable();

                $table->string(
                    'currency',
                    3
                )->default('GBP');

                /*
                 * Derived convenience value for reporting.
                 *
                 * The exact contractual amount above remains authority.
                 * Null for termination and one-off assertions.
                 */
                $table->decimal(
                    'monthly_equivalent',
                    15,
                    2
                )->nullable();

                $table->date(
                    'effective_from'
                );

                $table->date(
                    'effective_to'
                )->nullable();

                $table->date(
                    'renews_on'
                )->nullable();

                /*
                 * Explicit provenance of the human assertion.
                 *
                 * Examples:
                 * owner
                 * staff
                 * signed_agreement
                 * proposal
                 * email
                 * client_confirmation
                 */
                $table->string('source');

                $table->string(
                    'source_reference'
                )->nullable();

                $table
                    ->foreignId('reviewed_by')
                    ->nullable();

                /*
                 * Preserve human identity even if the User row is later
                 * removed and reviewed_by becomes null.
                 */
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
                 * Frozen exact state approved by the human.
                 */
                $table->json(
                    'terms_snapshot'
                );

                $table->json(
                    'metadata'
                )->nullable();

                /*
                 * No updated_at by design.
                 *
                 * Agreement assertions are append-only facts.
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
                        'supersedes_commercial_agreement_id'
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
                 * One immutable assertion may have at most one direct
                 * successor. This prevents history branching.
                 */
                $table->unique(
                    'supersedes_commercial_agreement_id',
                    'commercial_agreement_single_successor'
                );

                $table->index(
                    [
                        'client_id',
                        'client_service_id',
                    ],
                    'commercial_agreement_service'
                );

                $table->index(
                    [
                        'status',
                        'effective_from',
                    ],
                    'commercial_agreement_effective'
                );
            }
        );

        Schema::create(
            'commercial_agreement_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->uuid(
                    'commercial_agreement_id'
                );

                $table->string('type');
                $table->string('source');

                $table->string(
                    'reference'
                )->nullable();

                $table->text(
                    'summary'
                );

                $table->date(
                    'observed_on'
                )->nullable();

                $table->bigInteger(
                    'observed_value_pence'
                )->nullable();

                $table->string(
                    'currency',
                    3
                )->nullable();

                $table
                    ->unsignedTinyInteger(
                        'confidence'
                    )
                    ->default(50);

                $table
                    ->boolean('verified')
                    ->default(false);

                $table
                    ->foreignId('recorded_by')
                    ->nullable();

                $table->string(
                    'recorded_by_name'
                )->nullable();

                $table->timestamp(
                    'recorded_at'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                /*
                 * Evidence is append-only too.
                 */
                $table->timestamp(
                    'created_at'
                )->useCurrent();

                $table
                    ->foreign(
                        'commercial_agreement_id'
                    )
                    ->references('id')
                    ->on('commercial_agreements')
                    ->restrictOnDelete();

                $table
                    ->foreign('recorded_by')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();

                $table->index(
                    [
                        'commercial_agreement_id',
                        'type',
                    ],
                    'commercial_agreement_evidence_type'
                );
            }
        );

        /*
         * Database-level append-only protection.
         *
         * Eloquent model events protect normal application writes,
         * but query-builder / raw SQL writes bypass model events.
         *
         * Contract truth must remain immutable regardless of write path.
         */
        DB::statement(
            <<<'SQL'
CREATE TRIGGER commercial_agreements_immutable_update
BEFORE UPDATE ON commercial_agreements
BEGIN
    SELECT RAISE(
        ABORT,
        'Commercial agreement assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER commercial_agreements_immutable_delete
BEFORE DELETE ON commercial_agreements
BEGIN
    SELECT RAISE(
        ABORT,
        'Commercial agreement assertions are immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER commercial_agreement_evidence_immutable_update
BEFORE UPDATE ON commercial_agreement_evidence
BEGIN
    SELECT RAISE(
        ABORT,
        'Commercial agreement evidence is immutable'
    );
END
SQL
        );

        DB::statement(
            <<<'SQL'
CREATE TRIGGER commercial_agreement_evidence_immutable_delete
BEFORE DELETE ON commercial_agreement_evidence
BEGIN
    SELECT RAISE(
        ABORT,
        'Commercial agreement evidence is immutable'
    );
END
SQL
        );
    }

    public function down(): void
    {
        /*
         * Never destroy real contracted truth during rollback.
         *
         * A rollback is permitted only while the hardened tables are
         * still completely empty.
         */
        if (
            DB::table(
                'commercial_agreement_evidence'
            )->count() > 0
            || DB::table(
                'commercial_agreements'
            )->count() > 0
        ) {
            throw new RuntimeException(
                'Cannot roll back hardened commercial agreement schema after contracted truth has been recorded.'
            );
        }

        Schema::dropIfExists(
            'commercial_agreement_evidence'
        );

        Schema::dropIfExists(
            'commercial_agreements'
        );

        /*
         * Restore the exact legacy empty schema.
         */
        Schema::create(
            'commercial_agreements',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->foreignUuid('client_id')
                    ->constrained('clients')
                    ->cascadeOnDelete();

                $table->string(
                    'service_type'
                );

                $table->string(
                    'service_key'
                )->nullable();

                $table->string('cadence');

                $table
                    ->string('status')
                    ->default('candidate');

                $table
                    ->decimal(
                        'observed_value',
                        12,
                        2
                    )
                    ->default(0);

                $table
                    ->decimal(
                        'monthly_equivalent',
                        12,
                        2
                    )
                    ->default(0);

                $table
                    ->unsignedTinyInteger(
                        'confidence'
                    )
                    ->default(50);

                $table
                    ->date('starts_on')
                    ->nullable();

                $table
                    ->date('renews_on')
                    ->nullable();

                $table
                    ->string('source')
                    ->default(
                        'commercial_inference'
                    );

                $table
                    ->text('reason')
                    ->nullable();

                $table
                    ->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'client_id',
                    'service_type',
                    'status',
                ]);
            }
        );

        Schema::create(
            'commercial_agreement_evidence',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table
                    ->foreignUuid(
                        'commercial_agreement_id'
                    )
                    ->constrained(
                        'commercial_agreements'
                    )
                    ->cascadeOnDelete();

                $table->string('type');

                $table
                    ->string('reference')
                    ->nullable();

                $table->text('summary');

                $table
                    ->date('observed_on')
                    ->nullable();

                $table
                    ->decimal(
                        'observed_value',
                        12,
                        2
                    )
                    ->nullable();

                $table
                    ->unsignedTinyInteger(
                        'confidence'
                    )
                    ->default(50);

                $table
                    ->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'commercial_agreement_id',
                    'type',
                ]);
            }
        );
    }

    private function assertLegacyTablesAreEmpty(): void
    {
        $agreements =
            Schema::hasTable(
                'commercial_agreements'
            )
                ? DB::table(
                    'commercial_agreements'
                )->count()
                : 0;

        $evidence =
            Schema::hasTable(
                'commercial_agreement_evidence'
            )
                ? DB::table(
                    'commercial_agreement_evidence'
                )->count()
                : 0;

        if (
            $agreements !== 0
            || $evidence !== 0
        ) {
            throw new RuntimeException(
                'Commercial agreement hardening requires the legacy agreement tables to be empty.'
            );
        }
    }
};
