<?php

namespace Tests\Feature;

use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementReviewManifestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_command_is_read_only(): void
    {
        $before = [
            'agreements' => CommercialAgreement::count(),

            'coverage' => CommercialAgreementCoverageReview::count(),
        ];

        $this->artisan(
            'money:contract-review-manifest',
            [
                '--as-of' => '2026-09-03',
            ]
        )
            ->expectsOutputToContain(
                'Contract Review Manifest'
            )
            ->expectsOutputToContain(
                'OBSERVED BILLING IS NOT CONTRACT TRUTH'
            )
            ->expectsOutputToContain(
                'NO CONTRACTUAL OR COVERAGE TRUTH WAS WRITTEN.'
            )
            ->assertSuccessful();

        $after = [
            'agreements' => CommercialAgreement::count(),

            'coverage' => CommercialAgreementCoverageReview::count(),
        ];

        $this->assertSame(
            $before,
            $after
        );
    }
}
