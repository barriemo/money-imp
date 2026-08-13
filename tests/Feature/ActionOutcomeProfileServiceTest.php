<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Learning\ActionOutcomeProfileService;
use App\Models\Client;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionOutcomeProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_actions_create_deterministic_learning_profile(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Learning Client',
            ]);

        ExecutiveAction::create([
            'fingerprint' => hash(
                'sha256',
                'learning-success'
            ),

            'client_id' => $client->id,

            'client' => $client->name,

            'type' => 'financial_opportunity',

            'title' => 'Recover overdue revenue',

            'description' => '£5,000 is overdue.',

            'recommended_action' => 'Chase client.',

            'estimated_financial_impact' => 5000,

            'confidence' => 100,

            'urgency' => 95,

            'score' => 98,

            'status' => 'completed',

            'started_at' => now()
                ->subHours(2),

            'completed_at' => now(),

            'outcome' => 'Client paid.',

            'financial_result' => 5000,
        ]);

        ExecutiveAction::create([
            'fingerprint' => hash(
                'sha256',
                'learning-no-financial-result'
            ),

            'client_id' => $client->id,

            'client' => $client->name,

            'type' => 'financial_opportunity',

            'title' => 'Recover another balance',

            'description' => 'Balance required review.',

            'recommended_action' => 'Review client.',

            'confidence' => 100,

            'urgency' => 80,

            'score' => 85,

            'status' => 'completed',

            'started_at' => now()
                ->subHours(4),

            'completed_at' => now(),

            'outcome' => 'No recovery recorded.',
        ]);

        $service =
            app(
                ActionOutcomeProfileService::class
            );

        $profile =
            $service->forType(
                'financial_opportunity'
            );

        $this->assertNotNull(
            $profile
        );

        $this->assertSame(
            2,
            $profile->completedCount
        );

        $this->assertSame(
            1,
            $profile->financialSuccessCount
        );

        $this->assertSame(
            5000.0,
            $profile->totalFinancialResult
        );

        $this->assertSame(
            2500.0,
            $profile->averageFinancialResult
        );

        $this->assertSame(
            50,
            $profile->financialSuccessRate
        );

        $this->assertSame(
            3.0,
            $profile->averageCompletionHours
        );

        $clientProfile =
            $service->forClient(
                $client->id
            );

        $this->assertNotNull(
            $clientProfile
        );

        $this->assertSame(
            2,
            $clientProfile->completedCount
        );

        $this->assertSame(
            5000.0,
            $clientProfile->totalFinancialResult
        );
    }
}
