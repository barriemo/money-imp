<?php

namespace Tests\Feature;

use App\Models\ClientRequest;
use App\Models\ClientRequestClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_can_have_classifications(): void
    {
        $request = ClientRequest::factory()->create();

        ClientRequestClassification::factory()->create([
            'client_request_id' => $request->id,
        ]);

        $this->assertCount(
            1,
            $request->classifications
        );
    }

    public function test_classification_belongs_to_request(): void
    {
        $classification =
            ClientRequestClassification::factory()->create();

        $this->assertInstanceOf(
            ClientRequest::class,
            $classification->request
        );
    }

    public function test_classification_stores_confidence(): void
    {
        $classification =
            ClientRequestClassification::factory()->create([
                'confidence' => 95,
            ]);

        $this->assertSame(
            95,
            $classification->confidence
        );
    }

    public function test_classification_stores_reason(): void
    {
        $classification =
            ClientRequestClassification::factory()->create([
                'reason' => 'Referral opportunity detected.',
            ]);

        $this->assertSame(
            'Referral opportunity detected.',
            $classification->reason
        );
    }
}
