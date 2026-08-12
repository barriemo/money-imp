<?php

namespace Tests\Feature;

use App\Domains\VATIntelligence\VATExposureBuilder;
use App\Domains\VATIntelligence\VATPosition;
use Tests\TestCase;

class VATExposureBuilderTest extends TestCase
{
    public function test_vat_liability_creates_exposure(): void
    {
        $exposure =
            app(
                VATExposureBuilder::class
            )->build(
                new VATPosition(
                    vatCollected: 50000,

                    vatPaid: 20000,

                    dueDate: now()->addDays(28)
                )
            );

        $this->assertSame(
            30000.0,
            $exposure->liability
        );

        $this->assertSame(
            100,
            $exposure->priority
        );

        $this->assertSame(
            'VAT liability requires cash planning.',
            $exposure->reason
        );
    }
}
