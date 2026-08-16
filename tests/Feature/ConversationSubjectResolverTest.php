<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Conversation\ConversationSubjectResolver;
use Tests\TestCase;

class ConversationSubjectResolverTest extends TestCase
{
    public function test_client_can_be_selected_from_current_ledger_anomaly_candidates(): void
    {
        $context =
            new ConversationContext(
                issue: 'client_ledger_anomalies',
                unresolvedQuestions: [
                    [
                        'client_id' => 'peak-id',
                        'client_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                        'classification' => 'high_confidence_anomaly',
                        'difference' => -27600,
                        'priority' => 95,
                    ],
                    [
                        'client_id' => 'walker-id',
                        'client_name' => 'Walker The Jeweller Ltd',
                        'classification' => 'historical_evidence_incomplete',
                        'difference' => -46720.89,
                        'priority' => 70,
                    ],
                ]
            );

        $subject =
            app(
                ConversationSubjectResolver::class
            )->resolve(
                "let's do Peak",
                $context
            );

        $this->assertNotNull(
            $subject
        );

        $this->assertSame(
            'peak-id',
            $subject['client_id']
        );

        $this->assertSame(
            'PEAK RENEWABLES (SCOTLAND) LTD',
            $subject['client_name']
        );
    }
}
