<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommercialAgreementCoverageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coverage_schema_is_append_only_and_canonical_service_bound(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'commercial_agreement_coverage_reviews',
                [
                    'id',
                    'client_id',
                    'client_service_id',
                    'supersedes_commercial_agreement_coverage_review_id',
                    'outcome',
                    'commercial_agreement_id',
                    'effective_from',
                    'source',
                    'source_reference',
                    'reviewed_by',
                    'reviewed_by_name',
                    'reviewed_at',
                    'reason',
                    'evidence_snapshot',
                    'metadata',
                    'created_at',
                ]
            )
        );

        $this->assertFalse(
            Schema::hasColumn(
                'commercial_agreement_coverage_reviews',
                'updated_at'
            )
        );
    }

    public function test_coverage_schema_has_five_restrict_foreign_keys_and_two_immutable_triggers(): void
    {
        $foreignKeys =
            collect(
                DB::select(
                    'PRAGMA foreign_key_list(commercial_agreement_coverage_reviews)'
                )
            )
                ->keyBy('from');

        foreach (
            [
                'client_id',
                'client_service_id',
                'supersedes_commercial_agreement_coverage_review_id',
                'commercial_agreement_id',
                'reviewed_by',
            ] as $column
        ) {
            $this->assertSame(
                'RESTRICT',
                strtoupper(
                    $foreignKeys[
                        $column
                    ]->on_delete
                )
            );
        }

        $triggers =
            collect(
                DB::select(
                    "SELECT name
                     FROM sqlite_master
                     WHERE type = 'trigger'"
                )
            )
                ->pluck('name');

        $this->assertContains(
            'commercial_agreement_coverage_reviews_immutable_update',
            $triggers
        );

        $this->assertContains(
            'commercial_agreement_coverage_reviews_immutable_delete',
            $triggers
        );
    }
}
