<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Attribution\HostingKnowledgeGapService;
use App\Models\AttributionCandidate;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostingKnowledgeGapTest extends TestCase
{
    use RefreshDatabase;

    public function test_higher_value_hosting_gap_is_ranked_first(): void
    {
        $lowValueClient =
            Client::factory()->create();

        $highValueClient =
            Client::factory()->create();

        AttributionCandidate::create([
            'fingerprint' => hash(
                'sha256',
                'low'
            ),

            'subject_type' => 'client',

            'subject_id' => $lowValueClient->id,

            'relationship_type' => 'hosted_on',

            'target_type' => 'supplier_asset',

            'target_id' => null,

            'confidence' => 95,

            'status' => 'candidate',

            'source' => 'hosting_invoice_history',

            'evidence' => [
                [
                    'type' => 'invoice_history',

                    'summary' => 'Monthly Hosting',

                    'confidence' => 95,

                    'metadata' => [
                        'invoice_date' => '2026-07-31',

                        'monthly_rate' => 50,
                    ],
                ],
            ],
        ]);

        AttributionCandidate::create([
            'fingerprint' => hash(
                'sha256',
                'high'
            ),

            'subject_type' => 'client',

            'subject_id' => $highValueClient->id,

            'relationship_type' => 'hosted_on',

            'target_type' => 'supplier_asset',

            'target_id' => null,

            'confidence' => 95,

            'status' => 'candidate',

            'source' => 'hosting_invoice_history',

            'evidence' => [
                [
                    'type' => 'invoice_history',

                    'summary' => 'Monthly Hosting - Site A',

                    'confidence' => 95,

                    'metadata' => [
                        'invoice_date' => '2026-07-31',

                        'monthly_rate' => 75,
                    ],
                ],

                [
                    'type' => 'invoice_history',

                    'summary' => 'Monthly Hosting - Site B',

                    'confidence' => 95,

                    'metadata' => [
                        'invoice_date' => '2026-07-31',

                        'monthly_rate' => 75,
                    ],
                ],
            ],
        ]);

        $gaps = app(
            HostingKnowledgeGapService::class
        )->gaps();

        $this->assertCount(
            2,
            $gaps
        );

        $this->assertSame(
            $highValueClient->id,
            $gaps
                ->first()[
                    'client_id'
                ]
        );

        $this->assertSame(
            150.0,
            $gaps
                ->first()[
                    'monthly_revenue'
                ]
        );

        $this->assertGreaterThan(
            $gaps
                ->last()[
                    'score'
                ],
            $gaps
                ->first()[
                    'score'
                ]
        );
    }
}
