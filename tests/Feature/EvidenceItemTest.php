<?php

namespace Tests\Feature;

use App\Domains\Evidence\EvidenceItem;
use Tests\TestCase;

class EvidenceItemTest extends TestCase
{
    public function test_evidence_can_represent_verified_owner_knowledge(): void
    {
        $evidence =
            new EvidenceItem(
                type: 'commercial_agreement',
                source: 'owner',
                summary: 'Hosting is billed annually.',
                confidence: 100,
                verified: true
            );

        $this->assertTrue(
            $evidence->verified
        );

        $this->assertSame(
            100,
            $evidence->confidence
        );

        $this->assertSame(
            'owner',
            $evidence->source
        );
    }
}
