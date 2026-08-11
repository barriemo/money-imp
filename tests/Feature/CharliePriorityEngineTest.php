<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Enums\BusinessMemoryInsightType;
use App\Domains\CheerfulCharlie\Priority\CharliePriorityEngine;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryInsight;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharliePriorityEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_can_outrank_lower_confidence_opportunity(): void
    {
        $client =
            Client::factory()->create();

        $memory =
            BusinessMemory::create([
                'subject_type' => $client->getMorphClass(),

                'subject_id' => $client->id,

                'title' => $client->name,

                'status' => 'active',
            ]);

        $followUp =
            BusinessMemoryInsight::create([
                'business_memory_id' => $memory->id,

                'insight_type' => BusinessMemoryInsightType::FollowUp,

                'title' => 'Promise requires follow-up',

                'summary' => 'Proposal was promised.',

                'confidence' => 95,

                'priority' => 50,

                'status' => 'open',

                'source' => 'test',
            ]);

        $opportunity =
            BusinessMemoryInsight::create([
                'business_memory_id' => $memory->id,

                'insight_type' => BusinessMemoryInsightType::Opportunity,

                'title' => 'Commercial opportunity',

                'summary' => 'Possible future upsell.',

                'confidence' => 60,

                'priority' => 50,

                'status' => 'open',

                'source' => 'test',
            ]);

        $ranked = app(
            CharliePriorityEngine::class
        )->rank(
            collect([
                $opportunity,
                $followUp,
            ])
        );

        $this->assertSame(
            $followUp->id,
            $ranked
                ->first()
                ->insight
                ->id
        );

        $this->assertGreaterThan(
            $ranked
                ->last()
                ->score,
            $ranked
                ->first()
                ->score
        );
    }
}
