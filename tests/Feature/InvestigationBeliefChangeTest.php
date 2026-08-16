<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationBeliefChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_change_is_recorded_when_new_evidence_changes_belief(): void
    {
        $service =
            app(
                InvestigationCaseService::class
            );

        $case =
            $service->open(
                type: 'client_ledger',
                title: 'Peak HSBC investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $service->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'unverified',
            confidence: 0
        );

        $service->claimAssessmentEvent(
            case: $case,
            key: 'payment_destination_hsbc',
            statement: 'The payments were received into the HSBC account.',
            status: 'supported',
            confidence: 95,
            evidence: [
                [
                    'source' => 'bank_source',
                    'description' => 'Matching HSBC receipt found.',
                    'position' => 'supports',
                    'confidence' => 95,
                ],
            ]
        );

        $this->assertDatabaseHas(
            'investigation_events',
            [
                'investigation_case_id' => $case->id,
                'type' => 'claim_changed',
            ]
        );

        $changed =
            $case->events()
                ->where(
                    'type',
                    'claim_changed'
                )
                ->firstOrFail();

        $this->assertSame(
            'unverified',
            $changed->payload[
                'previous_status'
            ]
        );

        $this->assertSame(
            'supported',
            $changed->payload[
                'status'
            ]
        );

        $this->assertSame(
            95,
            $changed->payload[
                'confidence'
            ]
        );
    }
}
