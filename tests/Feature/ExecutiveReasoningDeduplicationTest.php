<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\ExecutiveActionFingerprint;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use Tests\TestCase;

class ExecutiveReasoningDeduplicationTest extends TestCase
{
    public function test_identical_executive_reasoning_is_deduplicated(): void
    {
        $first =
            new ExecutiveReasoning(
                type: 'cash_management',

                clientId: null,

                client: null,

                title: 'Review cash risk exposure',

                description: 'Cash position cannot be fully verified.',

                estimatedFinancialImpact: null,

                estimatedEffortMinutes: 30,

                confidence: 0,

                urgency: 50,

                score: 50,

                recommendedAction: 'Review cash position',

                supportingEvidence: []
            );

        $second =
            new ExecutiveReasoning(
                type: 'cash_management',

                clientId: null,

                client: null,

                title: 'Review cash risk exposure',

                description: 'Cash position cannot be fully verified.',

                estimatedFinancialImpact: null,

                estimatedEffortMinutes: 30,

                confidence: 0,

                urgency: 50,

                score: 50,

                recommendedAction: 'Review cash position',

                supportingEvidence: []
            );

        $fingerprint =
            app(
                ExecutiveActionFingerprint::class
            );

        $actions =
            collect([
                $first,
                $second,
            ])
                ->unique(
                    fn (ExecutiveReasoning $reasoning) => $fingerprint->make(
                        $reasoning
                    )
                );

        $this->assertCount(
            1,
            $actions
        );
    }
}
