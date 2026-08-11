<?php

namespace Tests\Feature;

use App\Models\AttributionCandidate;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributionCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_exist_without_confirmed_target(): void
    {
        $client =
            Client::factory()->create();

        $candidate =
            AttributionCandidate::create([
                'fingerprint' => hash(
                    'sha256',
                    'client|'
                    .$client->id
                    .'|hosted_on|supplier_asset|unknown'
                ),

                'subject_type' => 'client',

                'subject_id' => $client->id,

                'relationship_type' => 'hosted_on',

                'target_type' => 'supplier_asset',

                'target_id' => null,

                'confidence' => 95,

                'status' => 'candidate',

                'source' => 'invoice_history',

                'reason' => 'Recurring hosting invoice exists but server is unknown.',

                'evidence' => [
                [
                    'type' => 'invoice_history',

                    'summary' => 'Monthly hosting invoice',

                    'confidence' => 95,
                ],
                ],
            ]);

        $this->assertSame(
            'candidate',
            $candidate->status
        );

        $this->assertNull(
            $candidate->target_id
        );

        $this->assertSame(
            95,
            $candidate->confidence
        );
    }
}
