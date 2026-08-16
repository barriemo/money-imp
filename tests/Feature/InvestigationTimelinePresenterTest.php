<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Timeline\InvestigationTimelinePresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationTimelinePresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_investigation_can_be_presented_as_a_reasoning_timeline(): void
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

        $cases->event(
            case: $case,
            type: 'hypothesis_asserted',
            description: 'Those large invoices were paid into our old HSBC account.',
            actorType: 'user'
        );

        $case->forceFill([
            'current_hypothesis' => 'Those large invoices were paid into our old HSBC account.',
        ])->save();

        $cases->assessmentEvent(
            case: $case,
            hypothesis: 'Those large invoices were paid into our old HSBC account.',
            status: 'plausible',
            confidence: 60
        );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'unverified',
            confidence: 0
        );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'supported',
            confidence: 95
        );

        $cases->close(
            case: $case,
            verdict: 'The HSBC explanation was verified.',
            confidence: 100,
            reason: 'Matching HSBC evidence resolved the investigation.'
        );

        $output =
            app(
                InvestigationTimelinePresenter::class
            )->present(
                $case->refresh()
            );

        $this->assertStringContainsString(
            'PEAK RENEWABLES (SCOTLAND) LTD — CLOSED',
            $output
        );

        $this->assertStringContainsString(
            'Investigation history',
            $output
        );

        $this->assertStringContainsString(
            'Initial / historical investigation',
            $output
        );

        $this->assertStringContainsString(
            'Hypothesis',
            $output
        );

        $this->assertStringContainsString(
            'Claims',
            $output
        );

        $this->assertStringContainsString(
            'Outcome:',
            $output
        );

        $this->assertStringContainsString(
            'The HSBC explanation was verified.',
            $output
        );
    }

    public function test_correlated_reasoning_is_presented_as_evidence_episode(): void
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

        $metadata = [
            'correlation_id' => 'episode-123',
        ];

        $cases->event(
            case: $case,
            type: 'evidence_changed',
            description: 'FreeAgent bank transaction evidence changed.',
            payload: $metadata
        );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'unverified',
            confidence: 0
        );

        $cases->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'supported',
            confidence: 95,
            eventMetadata: $metadata
        );

        $output =
            app(
                InvestigationTimelinePresenter::class
            )->present(
                $case
            );

        $this->assertStringContainsString(
            'Evidence episode',
            $output
        );

        $this->assertStringContainsString(
            'FreeAgent bank transaction evidence changed.',
            $output
        );

        $this->assertStringContainsString(
            'UNVERIFIED',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTED',
            $output
        );
    }

    public function test_retracted_hypothesis_is_explicitly_marked_as_historical_correction(): void
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
            'current_hypothesis' => 'Synthetic development hypothesis.',
        ])->save();

        $case =
            $cases->correctHypothesis(
                case: $case,
                hypothesis: 'Historical bank coverage may be incomplete.',
                reason: 'The previous hypothesis was synthetic development data.'
            );

        $output =
            app(
                InvestigationTimelinePresenter::class
            )->present(
                $case
            );

        $this->assertStringContainsString(
            'Corrections',
            $output
        );

        $this->assertStringContainsString(
            'Retracted: Synthetic development hypothesis.',
            $output
        );

        $this->assertStringContainsString(
            'The previous hypothesis was synthetic development data.',
            $output
        );

        $this->assertStringContainsString(
            'Replaced with: Historical bank coverage may be incomplete.',
            $output
        );
    }
}
