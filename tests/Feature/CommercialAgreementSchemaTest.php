<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialAgreementSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_hardened_schema_has_canonical_service_and_append_only_fields(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'commercial_agreements',
                [
                    'client_id',
                    'client_service_id',
                    'supersedes_commercial_agreement_id',
                    'status',
                    'cadence',
                    'contracted_amount_pence',
                    'currency',
                    'monthly_equivalent',
                    'effective_from',
                    'effective_to',
                    'renews_on',
                    'source',
                    'source_reference',
                    'reviewed_by',
                    'reviewed_by_name',
                    'reviewed_at',
                    'reason',
                    'terms_snapshot',
                    'metadata',
                    'created_at',
                ]
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'commercial_agreements',
                'updated_at'
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'commercial_agreements',
                'observed_value'
            )
        );
    }

    public function test_contract_truth_foreign_keys_do_not_cascade_delete(): void
    {
        $agreementForeignKeys =
            collect(
                DB::select(
                    'PRAGMA foreign_key_list(commercial_agreements)'
                )
            )
                ->keyBy('from');

        $this->assertSame(
            'RESTRICT',
            strtoupper(
                $agreementForeignKeys[
                    'client_id'
                ]->on_delete
            )
        );

        $this->assertSame(
            'RESTRICT',
            strtoupper(
                $agreementForeignKeys[
                    'client_service_id'
                ]->on_delete
            )
        );

        $this->assertSame(
            'RESTRICT',
            strtoupper(
                $agreementForeignKeys[
                    'supersedes_commercial_agreement_id'
                ]->on_delete
            )
        );

        $this->assertSame(
            'SET NULL',
            strtoupper(
                $agreementForeignKeys[
                    'reviewed_by'
                ]->on_delete
            )
        );

        $evidenceForeignKeys =
            collect(
                DB::select(
                    'PRAGMA foreign_key_list(commercial_agreement_evidence)'
                )
            )
                ->keyBy('from');

        $this->assertSame(
            'RESTRICT',
            strtoupper(
                $evidenceForeignKeys[
                    'commercial_agreement_id'
                ]->on_delete
            )
        );

        $this->assertSame(
            'SET NULL',
            strtoupper(
                $evidenceForeignKeys[
                    'recorded_by'
                ]->on_delete
            )
        );
    }
}
