<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Client\Services\ClientRequestClassifier;
use App\Models\ClientRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestClassifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_request_is_classified(): void
    {
        $request = ClientRequest::factory()->create([
            'request' => 'Can you update the homepage content?',
        ]);

        $result = app(
            ClientRequestClassifier::class
        )->classify($request);

        $this->assertSame(
            'delivery_request',
            $result['classification']
        );
    }

    public function test_project_opportunity_is_classified(): void
    {
        $request = ClientRequest::factory()->create([
            'request' => 'We need a new website build.',
        ]);

        $result = app(
            ClientRequestClassifier::class
        )->classify($request);

        $this->assertSame(
            'project_opportunity',
            $result['classification']
        );
    }

    public function test_referral_opportunity_is_classified(): void
    {
        $request = ClientRequest::factory()->create([
            'request' => 'We know someone who might need your help.',
        ]);

        $result = app(
            ClientRequestClassifier::class
        )->classify($request);

        $this->assertSame(
            'referral_opportunity',
            $result['classification']
        );
    }
}
