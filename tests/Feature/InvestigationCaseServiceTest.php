<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Models\InvestigationCase;
use App\Models\InvestigationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationCaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_investigation_can_be_opened_with_immutable_timeline_start(): void
    {
        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Why does Peak Renewables not reconcile?',
                question: 'Why does the ledger not reconcile?',
                subjectType: 'client',
                subjectId: 'peak-client-id',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $this->assertInstanceOf(
            InvestigationCase::class,
            $case
        );

        $this->assertSame(
            'open',
            $case->status
        );

        $this->assertSame(
            'client',
            $case->subject_type
        );

        $this->assertSame(
            'peak-client-id',
            $case->subject_id
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'case_opened',
                'actor_type' => 'business_brain',
            ]
        );

        $event =
            InvestigationEvent::query()
                ->where(
                    'investigation_case_id',
                    $case->id
                )
                ->firstOrFail();

        $this->assertSame(
            'Why does the ledger not reconcile?',
            $event->description
        );
    }

    public function test_additional_events_are_appended_to_existing_case(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak ledger investigation'
            );

        $service->event(
            case: $case,
            type: 'hypothesis_asserted',
            description: 'Large invoices were paid into the old HSBC account.',
            actorType: 'user',
            payload: [
                'status' => 'asserted',
            ]
        );

        $this->assertSame(
            2,
            $case
                ->events()
                ->count()
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'hypothesis_asserted',
                'actor_type' => 'user',
            ]
        );
    }

    public function test_existing_open_case_is_reused_for_same_subject(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $first =
            $service->findOrOpenForSubject(
                type: 'client_ledger',
                subjectType: 'client',
                subjectId: 'peak-client-id',
                subjectName: 'Peak Renewables'
            );

        $second =
            $service->findOrOpenForSubject(
                type: 'client_ledger',
                subjectType: 'client',
                subjectId: 'peak-client-id',
                subjectName: 'Peak Renewables'
            );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            InvestigationCase::query()
                ->count()
        );
    }

    public function test_identical_hypothesis_assessment_is_not_duplicated(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak investigation'
            );

        $service->assessmentEvent(
            case: $case,
            hypothesis: 'Paid into HSBC',
            status: 'plausible',
            confidence: 60
        );

        $service->assessmentEvent(
            case: $case,
            hypothesis: 'Paid into HSBC',
            status: 'plausible',
            confidence: 60
        );

        $this->assertSame(
            1,
            $case->events()
                ->where(
                    'type',
                    'hypothesis_assessed'
                )
                ->count()
        );
    }

    public function test_changed_hypothesis_assessment_creates_change_event(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak investigation'
            );

        $service->assessmentEvent(
            case: $case,
            hypothesis: 'Paid into HSBC',
            status: 'plausible',
            confidence: 70
        );

        $service->assessmentEvent(
            case: $case,
            hypothesis: 'Paid into HSBC',
            status: 'plausible',
            confidence: 60
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'hypothesis_changed',
            ]
        );
    }

    public function test_identical_claim_assessment_is_not_duplicated(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak investigation'
            );

        $service->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payment was received into HSBC.',
            status: 'unverified',
            confidence: 0
        );

        $service->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payment was received into HSBC.',
            status: 'unverified',
            confidence: 0
        );

        $this->assertSame(
            1,
            $case->events()
                ->where(
                    'type',
                    'claim_assessed'
                )
                ->count()
        );
    }

    public function test_changed_claim_assessment_creates_change_event(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak investigation'
            );

        $service->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payment was received into HSBC.',
            status: 'unverified',
            confidence: 0
        );

        $service->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payment was received into HSBC.',
            status: 'supported',
            confidence: 95
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'claim_changed',
            ]
        );
    }

    public function test_verified_investigation_can_be_closed_once(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak investigation'
            );

        $service->close(
            case: $case,
            verdict: 'The HSBC hypothesis is verified.',
            confidence: 100,
            reason: 'Required bank evidence has been verified.'
        );

        $service->close(
            case: $case->refresh(),
            verdict: 'The HSBC hypothesis is verified.',
            confidence: 100,
            reason: 'Required bank evidence has been verified.'
        );

        $case->refresh();

        $this->assertSame(
            'closed',
            $case->status
        );

        $this->assertSame(
            100,
            $case->confidence
        );

        $this->assertNotNull(
            $case->closed_at
        );

        $this->assertSame(
            1,
            $case->events()
                ->where(
                    'type',
                    'case_closed'
                )
                ->count()
        );
    }

    public function test_incorrect_hypothesis_can_be_retracted_without_rewriting_history(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'Peak'
            );

        $case->forceFill([
            'current_hypothesis' => 'Synthetic development hypothesis.',
        ])->save();

        $corrected =
            $service->correctHypothesis(
                case: $case,
                hypothesis: 'Historical bank coverage may be incomplete.',
                reason: 'The previous hypothesis was synthetic development data and is not supported as business truth.'
            );

        $this->assertSame(
            'Historical bank coverage may be incomplete.',
            $corrected->current_hypothesis
        );

        $this->assertSame(
            'testing',
            $corrected->status
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'hypothesis_retracted',
                'description' => 'Synthetic development hypothesis.',
            ]
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'hypothesis_asserted',
                'description' => 'Historical bank coverage may be incomplete.',
            ]
        );
    }
}
