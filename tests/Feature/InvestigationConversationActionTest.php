<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Conversation\InvestigationConversationAction;
use App\Models\InvestigationCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationConversationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_investigation_can_be_recalled_conversationally(): void
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
                InvestigationConversationAction::class
            )->execute(
                'show me the Peak investigation',
                new ConversationContext
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'PEAK RENEWABLES (SCOTLAND) LTD',
            $answer->answer
        );

        $this->assertStringContainsString(
            'Investigation history',
            $answer->answer
        );

        $this->assertSame(
            $case->id,
            $answer->context->investigationCaseId
        );

        $this->assertSame(
            'investigation_history',
            $answer->context->issue
        );
    }

    public function test_current_subject_can_recall_latest_investigation(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                'what happened with this investigation?',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertSame(
            $case->id,
            $answer->context->investigationCaseId
        );
    }

    public function test_latest_conclusion_can_be_recalled_from_investigation_context(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $case->forceFill([
            'current_hypothesis' => 'Those invoices were paid into HSBC.',
            'status' => 'waiting',
            'confidence' => 60,
            'verdict' => 'The hypothesis is plausible, but evidence is missing.',
        ])->save();

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                'show me the latest conclusion',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                    issue: 'investigation_history',
                    investigationCaseId: $case->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            '60% confidence',
            $answer->answer
        );

        $this->assertStringContainsString(
            'Those invoices were paid into HSBC.',
            $answer->answer
        );
    }

    public function test_missing_evidence_can_be_recalled_from_investigation(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $cases->assessmentEvent(
            case: $case,
            hypothesis: 'Those invoices were paid into HSBC.',
            status: 'plausible',
            confidence: 60,
            payload: [
                'missing_evidence' => [
                    'No HSBC bank account is currently represented in Money Imp.',
                ],
            ]
        );

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                'what evidence is still missing?',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    issue: 'investigation_history',
                    investigationCaseId: $case->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'No HSBC bank account',
            $answer->answer
        );
    }

    public function test_historical_assessment_change_can_be_explained(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $cases->event(
            case: $case,
            type: 'hypothesis_assessed',
            description: 'HSBC hypothesis — plausible (70%)',
            payload: [
                'status' => 'plausible',
                'confidence' => 70,
            ]
        );

        $cases->event(
            case: $case,
            type: 'hypothesis_assessed',
            description: 'HSBC hypothesis — plausible (60%)',
            payload: [
                'status' => 'plausible',
                'confidence' => 60,
                'missing_evidence' => [
                    'No HSBC bank account is currently represented in Money Imp.',
                ],
            ]
        );

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                'why did your conclusion change?',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    issue: 'investigation_history',
                    investigationCaseId: $case->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            '70% confidence',
            $answer->answer
        );

        $this->assertStringContainsString(
            '60% confidence',
            $answer->answer
        );
    }

    public function test_causal_evidence_change_is_preferred_when_explaining_changed_conclusion(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'unverified',
            confidence: 0
        );

        $cases->assessmentEvent(
            case: $case,
            hypothesis: 'Those invoices were paid into HSBC.',
            status: 'plausible',
            confidence: 60
        );

        $cases->event(
            case: $case,
            type: 'evidence_changed',
            description: 'FreeAgent bank transaction evidence changed.',
            payload: [
                'domain' => 'bank',
                'type' => 'bank_transactions_changed',
            ]
        );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'supported',
            confidence: 95
        );

        $cases->assessmentEvent(
            case: $case,
            hypothesis: 'Those invoices were paid into HSBC.',
            status: 'verified',
            confidence: 95
        );

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                'why did your conclusion change?',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    issue: 'investigation_history',
                    investigationCaseId: $case->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'FreeAgent bank transaction evidence changed.',
            $answer->answer
        );

        $this->assertStringContainsString(
            'UNVERIFIED',
            $answer->answer
        );

        $this->assertStringContainsString(
            'SUPPORTED',
            $answer->answer
        );

        $this->assertStringContainsString(
            'PLAUSIBLE',
            $answer->answer
        );

        $this->assertStringContainsString(
            'VERIFIED',
            $answer->answer
        );
    }

    public function test_latest_conclusion_does_not_carry_claims_from_retracted_hypothesis(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Ledger investigation',
                subjectType: 'client',
                subjectId: 'client-1',
                subjectName: 'Example Client'
            );

        $case->forceFill([
            'current_hypothesis' => 'Synthetic historical hypothesis.',
        ])->save();

        $cases->event(
            case: $case,
            type: 'hypothesis_asserted',
            description: 'Synthetic historical hypothesis.',
            actorType: 'user'
        );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'synthetic_destination',
            statement: 'The payment went to a synthetic bank account.',
            status: 'unverified',
            confidence: 0
        );

        $case =
            $cases->correctHypothesis(
                case: $case,
                hypothesis: 'Available bank evidence may be incomplete.',
                reason: 'The previous hypothesis was synthetic.'
            );

        $this->assertNotNull(
            $case->metadata[
                'hypothesis_version'
            ]
            ?? null
        );

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                'show me the latest conclusion',
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'client-1',
                    issue: 'investigation_history',
                    investigationCaseId: $case->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertStringContainsString(
            'Available bank evidence may be incomplete.',
            $answer->answer
        );

        $this->assertStringNotContainsString(
            'synthetic bank account',
            $answer->answer
        );
    }

    public function test_explicit_named_investigation_overrides_previous_case_context(): void
    {
        $peak =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'peak',
                'subject_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                'title' => 'Peak investigation',
                'status' => 'waiting',
                'confidence' => 70,
                'opened_at' => now()
                    ->subMinute(),
            ]);

        $burtys =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'burtys',
                'subject_name' => "Burty's Timber",
                'title' => "Investigate ledger anomaly for Burty's Timber",
                'status' => 'open',
                'confidence' => 0,
                'opened_at' => now(),
            ]);

        $answer =
            app(
                InvestigationConversationAction::class
            )->execute(
                "show me the Burty's investigation",
                new ConversationContext(
                    subjectType: 'client',
                    subjectId: 'peak',
                    subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                    issue: 'investigation_history',
                    investigationCaseId: $peak->id
                )
            );

        $this->assertNotNull(
            $answer
        );

        $this->assertSame(
            $burtys->id,
            $answer->context
                ->investigationCaseId
        );

        $this->assertStringContainsString(
            "Burty's Timber",
            $answer->answer
        );

        $this->assertStringNotContainsString(
            'PEAK RENEWABLES',
            $answer->answer
        );
    }

    public function test_natural_named_investigation_overrides_previous_context(): void
    {
        $peak =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'peak',
                'subject_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                'title' => 'Peak ledger investigation',
                'status' => 'open',
                'confidence' => 50,
                'opened_at' => now()->subDay(),
            ]);

        $burtys =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'burtys',
                'subject_name' => "BURTY'S",
                'title' => "Burty's ledger investigation",
                'status' => 'open',
                'confidence' => 75,
                'opened_at' => now(),
            ]);

        $context =
            new ConversationContext;

        $context->investigationCaseId =
            $peak->id;

        $response =
            app(
                InvestigationConversationAction::class
            )->execute(
                "Can you show me the Burty's investigation?",
                $context
            );

        $this->assertNotNull(
            $response
        );

        $this->assertSame(
            $burtys->id,
            $context->investigationCaseId
        );
    }

    public function test_tell_me_about_named_investigation_overrides_previous_context(): void
    {
        $peak =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'peak',
                'subject_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                'title' => 'Peak ledger investigation',
                'status' => 'open',
                'confidence' => 50,
                'opened_at' => now()->subDay(),
            ]);

        $burtys =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'burtys',
                'subject_name' => "BURTY'S",
                'title' => "Burty's ledger investigation",
                'status' => 'open',
                'confidence' => 75,
                'opened_at' => now(),
            ]);

        $context =
            new ConversationContext;

        $context->investigationCaseId =
            $peak->id;

        $response =
            app(
                InvestigationConversationAction::class
            )->execute(
                "Tell me about Burty's investigation",
                $context
            );

        $this->assertNotNull(
            $response
        );

        $this->assertSame(
            $burtys->id,
            $context->investigationCaseId
        );
    }

    public function test_unknown_explicit_named_investigation_does_not_fall_back_to_previous_context(): void
    {
        $peak =
            InvestigationCase::create([
                'type' => 'client_ledger',
                'subject_type' => 'client',
                'subject_id' => 'peak',
                'subject_name' => 'PEAK RENEWABLES (SCOTLAND) LTD',
                'title' => 'Peak ledger investigation',
                'status' => 'open',
                'confidence' => 50,
                'opened_at' => now(),
            ]);

        $context =
            new ConversationContext;

        $context->investigationCaseId =
            $peak->id;

        $response =
            app(
                InvestigationConversationAction::class
            )->execute(
                'Show me the Acme Widgets investigation',
                $context
            );

        $this->assertNotNull(
            $response
        );

        $this->assertStringContainsString(
            'could not find',
            strtolower(
                $response->answer
            )
        );

        $this->assertSame(
            $peak->id,
            $context->investigationCaseId
        );
    }

    public function test_this_investigation_uses_current_subject_context(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $response =
            app(
                InvestigationConversationAction::class
            )->execute(
                'what happened with this investigation?',
                $context
            );

        $this->assertNotNull(
            $response
        );

        $this->assertSame(
            $case->id,
            $context->investigationCaseId
        );
    }
}
