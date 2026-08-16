<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBrief;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBrief;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\BusinessBrain\Cfo\Conversation\CfoConversationAction;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class CfoConversationActionTest extends TestCase
{
    public function test_cfo_can_explain_uncertainty(): void
    {
        $this->mock(
            CfoBriefService::class,
            function ($mock): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        new CfoBrief(
                            financialPosition: Mockery::mock(FinancialPosition::class),
                            businessBrain: Mockery::mock(BusinessBrainBrief::class),
                            overallStatus: 'UNCERTAIN',
                            overallConfidence: 0,
                            strengths: [],
                            risks: [
                                'Cash evidence is incomplete.',
                            ],
                            unknowns: [
                                'Safe available cash cannot yet be established.',
                                'Collectible receivables have not been verified.',
                            ],
                            priorities: [
                                'Verify outstanding liabilities.',
                            ],
                            recommendations: [],
                            questions: [],
                            bestNextVerification: null,
                            asOf: CarbonImmutable::now()
                        )
                    );
            }
        );

        $action =
            app(CfoConversationAction::class);

        $response =
            $action->execute(
                'Why are you uncertain?',
                new ConversationContext
            );

        $this->assertNotNull(
            $response
        );

        $this->assertStringContainsString(
            'Safe available cash cannot yet be established.',
            $response->answer
        );
    }

    public function test_non_cfo_question_returns_null(): void
    {
        $action =
            app(CfoConversationAction::class);

        $response =
            $action->execute(
                'Tell me about a customer payment',
                new ConversationContext
            );

        $this->assertNull(
            $response
        );
    }
}
