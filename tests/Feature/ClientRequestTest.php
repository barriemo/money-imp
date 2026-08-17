<?php

namespace Tests\Feature;

use App\Models\ClientRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_request_can_be_created(): void
    {
        $request = ClientRequest::factory()->create([
            'client_name' => 'Walker',
            'request' => 'Update homepage content',
        ]);

        $this->assertSame(
            'Walker',
            $request->client_name
        );

        $this->assertSame(
            'received',
            $request->status
        );
    }

    public function test_request_can_be_classified(): void
    {
        $request = ClientRequest::factory()->create([
            'classification' => 'website_update',
        ]);

        $this->assertSame(
            'website_update',
            $request->classification
        );
    }
}
