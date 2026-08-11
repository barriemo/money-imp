<?php

namespace Tests\Feature;

use App\Domains\Attribution\AttributionResolver;
use App\Models\AttributionCandidate;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_corroborating_evidence_strengthens_existing_candidate(): void
    {
        $client =
            Client::factory()->create();

        $resolver = app(
            AttributionResolver::class
        );

        $first =
            $resolver->propose(
                subjectType: 'client',
                subjectId: $client->id,
                relationshipType: 'hosted_on',
                targetType: 'supplier_asset',
                source: 'invoice_history',
                reason: 'Hosting is billed but server is unknown.',
                evidence: [
                    [
                        'type' => 'invoice_history',

                        'summary' => 'Monthly hosting invoice',

                        'confidence' => 80,
                    ],
                ]
            );

        $second =
            $resolver->propose(
                subjectType: 'client',
                subjectId: $client->id,
                relationshipType: 'hosted_on',
                targetType: 'supplier_asset',
                source: 'managed_service',
                evidence: [
                    [
                        'type' => 'managed_service',

                        'summary' => 'Managed Hosting service exists',

                        'confidence' => 90,
                    ],
                ]
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            AttributionCandidate::query()
                ->count()
        );

        $this->assertCount(
            2,
            $second->evidence
        );

        $this->assertGreaterThan(
            90,
            $second->confidence
        );

        $this->assertLessThanOrEqual(
            99,
            $second->confidence
        );
    }
}
