<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessObservation;
use App\Domains\Evidence\EvidenceItem;
use Tests\TestCase;

class BusinessObservationTest extends TestCase
{
    public function test_observation_can_be_supported_by_evidence(): void
    {
        $observation =
            new BusinessObservation(
                type: 'cash_confidence',
                summary: 'Bank balances are incomplete.',
                confidence: 40
            );

        $observation->addEvidence(
            new EvidenceItem(
                type: 'bank_balance',
                source: 'bank',
                summary: 'RBS balance is not verified.',
                confidence: 40
            )
        );

        $this->assertCount(
            1,
            $observation->evidence
        );
    }
}
