<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBrief;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBrief;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Query\BusinessBrainQueryService;
use App\Domains\BusinessBrain\Responses\BusinessResponse;
use App\Models\BusinessExperience;
use App\Models\InvestigationCase;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BusinessBrainQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_brain_can_answer_customer_payment_question_conversationally(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'business',
                subjectId: 'current-business',
                subjectName: 'Current Business'
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'How are customer payments looking?',
                $context
            );

        $this->assertInstanceOf(
            BusinessResponse::class,
            $answer
        );

        $this->assertSame(
            'Customer Payment Truth',
            $answer->insight->headline
        );

        $this->assertSame(
            'Current Business',
            $answer->context->subjectName
        );

        $this->assertSame(
            'customer_payment_truth',
            $answer->context->issue
        );
    }

    public function test_unknown_question_returns_null(): void
    {
        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'Tell me about the weather'
            );

        $this->assertNull(
            $answer
        );
    }

    public function test_payment_truth_follow_up_uses_conversation_context(): void
    {
        $context =
            new ConversationContext(
                issue: 'customer_payment_truth'
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'Why is confidence only 52%?',
                $context
            );

        $this->assertInstanceOf(
            BusinessResponse::class,
            $answer
        );

        $this->assertStringContainsString(
            'Confidence is',
            $answer->answer
        );

        $this->assertStringContainsString(
            'confirmed against invoices',
            $answer->answer
        );

        $this->assertSame(
            'customer_payment_truth',
            $answer->context->issue
        );
    }

    public function test_payment_truth_biggest_problem_follow_up_uses_current_subject(): void
    {
        $context =
            new ConversationContext(
                issue: 'customer_payment_truth'
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'What is the biggest problem?',
                $context
            );

        $this->assertStringContainsString(
            'largest unresolved payment problem',
            $answer->answer
        );
    }

    public function test_ledger_anomaly_context_can_show_anomaly_list_again_without_selecting_client(): void
    {
        $context =
            new ConversationContext(
                issue: 'client_ledger_anomalies'
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'Show me the biggest client-ledger anomalies',
                $context
            );

        $this->assertInstanceOf(
            BusinessResponse::class,
            $answer
        );

        $this->assertStringContainsString(
            'highest-priority client-ledger issues',
            $answer->answer
        );

        $this->assertSame(
            'client_ledger_anomalies',
            $answer->context->issue
        );
    }

    public function test_awaiting_user_assertion_routes_back_to_selected_client_conversation(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                issue: 'awaiting_user_assertion'
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'Those large invoices were paid into our old HSBC account.',
                $context
            );

        $this->assertInstanceOf(
            BusinessResponse::class,
            $answer
        );

        $this->assertSame(
            'client_ledger_assertion',
            $answer->context->issue
        );

        $this->assertSame(
            'Those large invoices were paid into our old HSBC account.',
            $answer->context->hypothesis
        );

        $this->assertStringContainsString(
            'not confirmed financial truth',
            $answer->answer
        );
    }

    public function test_ledger_anomaly_candidate_can_become_selected_client_subject(): void
    {
        $context =
            new ConversationContext(
                issue: 'client_ledger_anomalies',

                unresolvedQuestions: [
                    [
                        'client_id' => 'peak-client-id',
                        'client_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                        'classification' => 'high_confidence_anomaly',
                        'difference' => -27600,
                        'priority' => 95,
                    ],
                    [
                        'client_id' => 'walker-client-id',
                        'client_name' => 'Walker The Jeweller Ltd',
                        'classification' => 'historical_evidence_incomplete',
                        'difference' => -46720.89,
                        'priority' => 70,
                    ],
                ]
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                "let's do Peak",
                $context
            );

        $this->assertInstanceOf(
            BusinessResponse::class,
            $answer
        );

        $this->assertSame(
            'client',
            $answer->context->subjectType
        );

        $this->assertSame(
            'peak-client-id',
            $answer->context->subjectId
        );

        $this->assertSame(
            'PEAK RENEWABLES (SCOTLAND) LTD',
            $answer->context->subjectName
        );

        $this->assertSame(
            'client_ledger_anomaly',
            $answer->context->issue
        );
    }

    public function test_investigation_history_question_uses_persisted_case(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Why does Peak not reconcile?',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $case->forceFill([
            'current_hypothesis' => 'Those invoices were paid into HSBC.',
            'confidence' => 60,
            'status' => 'waiting',
        ])->save();

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'show me the Peak investigation'
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'Investigation history',
            $answer->answer
        );

        $this->assertStringContainsString(
            'PEAK RENEWABLES (SCOTLAND) LTD',
            $answer->answer
        );

        $this->assertSame(
            $case->id,
            $answer->context->investigationCaseId
        );
    }

    public function test_investigation_history_context_can_return_to_selected_client_conversation(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                issue: 'investigation_history',
                investigationCaseId: 'peak-case'
            );

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                "let's do Peak",
                $context
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertSame(
            'peak',
            $answer->context->subjectId
        );
    }

    public function test_business_brain_can_recall_similar_experience_from_current_investigation(): void
    {
        $current =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'current-client',
                'subject_name' => 'Current Client',
                'title' => 'Current ledger investigation',
                'status' => 'waiting',
                'confidence' => 60,
                'current_hypothesis' => 'Historical bank evidence may be incomplete.',
                'opened_at' => now(),
            ]);

        $historical =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'historical-client',
                'subject_name' => 'Historical Client',
                'title' => 'Historical ledger investigation',
                'status' => 'closed',
                'confidence' => 95,
                'opened_at' => now()->subMonth(),
                'closed_at' => now()->subMonth()->addHour(),
            ]);

        BusinessExperience::create([
            'source_investigation_case_id' => $historical->id,
            'fingerprint' => hash(
                'sha256',
                'query-service-experience'
            ),
            'type' => 'client_ledger',
            'subject_type' => 'client',
            'subject_id' => 'historical-client',
            'subject_name' => 'Historical Client',
            'title' => 'Historical ledger investigation',
            'summary' => 'Historical bank evidence was incomplete.',
            'outcome' => 'Importing missing historical bank evidence resolved the apparent ledger difference.',
            'confidence' => 95,
            'importance' => 80,
            'hypothesis' => 'Historical bank evidence may be incomplete.',
            'lessons' => [
                'Check historical bank coverage first.',
            ],
            'evidence_summary' => [],
            'experienced_at' => now()->subMonth(),
        ]);

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'have we seen this before?',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'current-client',
                    subjectName: 'Current Client',
                    issue: 'client_ledger_investigation',
                    investigationCaseId: $current->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'Historical Client',
            $answer->answer
        );

        $this->assertStringContainsString(
            'Previous outcome:',
            $answer->answer
        );
    }

    public function test_investigation_history_keeps_precedence_over_experience_routing(): void
    {
        $case =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'peak',
                'subject_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                'title' => 'Peak investigation',
                'status' => 'waiting',
                'confidence' => 60,
                'current_hypothesis' => 'Historical bank evidence may be incomplete.',
                'opened_at' => now(),
            ]);

        $answer =
            app(
                BusinessBrainQueryService::class
            )->ask(
                'show me the Peak investigation',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                    investigationCaseId: $case->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'Investigation history',
            $answer->answer
        );
    }

    public function test_business_brain_routes_cfo_question_to_cfo_conversation(): void
    {
        $this->mock(
            CfoBriefService::class,
            function ($mock): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        new CfoBrief(
                            financialPosition: Mockery::mock(
                                FinancialPosition::class
                            ),
                            businessBrain: Mockery::mock(
                                BusinessBrainBrief::class
                            ),
                            overallStatus: 'UNCERTAIN',
                            overallConfidence: 0,
                            strengths: [],
                            risks: [],
                            unknowns: [
                                'Safe available cash cannot yet be established.',
                            ],
                            priorities: [],
                            recommendations: [],
                            questions: [],
                            bestNextVerification: null,
                            asOf: CarbonImmutable::now()
                        )
                    );
            }
        );

        $response =
            app(
                BusinessBrainQueryService::class
            )
                ->ask(
                    'Why are you uncertain about the financial position?'
                );

        $this->assertInstanceOf(
            BusinessResponse::class,
            $response
        );

        $this->assertStringContainsString(
            'Safe available cash cannot yet be established.',
            $response->answer
        );
    }
}
