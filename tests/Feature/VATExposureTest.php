<?php

namespace Tests\Feature;

use App\Domains\VATIntelligence\VATPosition;
use Tests\TestCase;

class VATExposureTest extends TestCase
{
    public function test_vat_position_calculates_liability(): void
    {
        $position =
            new VATPosition(
                vatCollected: 50000,

                vatPaid: 20000,

                dueDate: now()->addDays(28)
            );

        $this->assertSame(
            30000.0,
            $position->liability()
        );
    }
}
