<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Timeline\InvestigationEpisodeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationEpisodeBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_correlated_events_are_grouped_into_single_causal_episode(): void
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

        $cases->assessmentEvent(
            case: $case,
            hypothesis: 'Those invoices were paid into HSBC.',
            status: 'plausible',
            confidence: 60
        );

        $cases->assessmentEvent(
            case: $case,
            hypothesis: 'Those invoices were paid into HSBC.',
            status: 'verified',
            confidence: 95,
            eventMetadata: $metadata
        );

        $episodes =
            app(
                InvestigationEpisodeBuilder::class
            )->build(
                $case
            );

        $episode =
            $episodes
                ->first(
                    fn ($episode) => $episode->correlationId
                        === 'episode-123'
                );

        $this->assertNotNull(
            $episode
        );

        $this->assertSame(
            'FreeAgent bank transaction evidence changed.',
            $episode->trigger
        );

        $this->assertTrue(
            $episode->claimChanges
                ->contains(
                    fn ($event) => $event->type
                        === 'claim_changed'
                )
        );

        $this->assertTrue(
            $episode->hypothesisChanges
                ->contains(
                    fn ($event) => $event->type
                        === 'hypothesis_changed'
                )
        );

        $this->assertFalse(
            $episode->legacy
        );
    }

    public function test_uncorrelated_old_events_are_preserved_as_legacy_episode(): void
    {
        $cases =
            app(
                InvestigationCaseService::class
            );

        $case =
            $cases->open(
                type: 'client_ledger',
                title: 'Legacy Peak investigation',
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD'
            );

        $cases->event(
            case: $case,
            type: 'hypothesis_assessed',
            description: 'Peak hypothesis — plausible (70%)'
        );

        $cases->event(
            case: $case,
            type: 'hypothesis_assessed',
            description: 'Peak hypothesis — plausible (60%)'
        );

        $episodes =
            app(
                InvestigationEpisodeBuilder::class
            )->build(
                $case
            );

        $legacy =
            $episodes
                ->first(
                    fn ($episode) => $episode->legacy
                );

        $this->assertNotNull(
            $legacy
        );

        $this->assertNull(
            $legacy->correlationId
        );

        $this->assertCount(
            2,
            $legacy->hypothesisChanges
        );
    }
}
