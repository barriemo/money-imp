<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Client\Services\ClientRequestClassificationService;
use App\Models\ClientRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifier_decision_is_persisted(): void
    {
        $request = ClientRequest::factory()->create([
            'request' => 'We need a new website build.',
        ]);

        $classification = app(
            ClientRequestClassificationService::class
        )->classify($request);

        $this->assertDatabaseHas(
            'client_request_classifications',
            [
                'id' => $classification->id,
            ]
        );

        $this->assertSame(
            'project_opportunity',
            $classification->classification
        );
    }

    public function test_persisted_classification_keeps_reason_and_confidence(): void
    {
        $request = ClientRequest::factory()->create([
            'request' => 'We know someone who might need your help.',
        ]);

        $classification = app(
            ClientRequestClassificationService::class
        )->classify($request);

        $this->assertNotNull(
            $classification->reason
        );

        $this->assertGreaterThan(
            0,
            $classification->confidence
        );
    }
}
