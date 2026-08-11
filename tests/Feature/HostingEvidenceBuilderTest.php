<?php

namespace Tests\Feature;

use App\Domains\Infrastructure\Attribution\HostingEvidenceBuilder;
use Tests\TestCase;

class HostingEvidenceBuilderTest extends TestCase
{
    public function test_hosting_invoice_line_becomes_structured_evidence(): void
    {
        $item = (object) [
            'description' => 'Monthly Hosting, Security Updates & Backups - Reforj',

            'unit_price' => 75,
        ];

        $evidence = app(
            HostingEvidenceBuilder::class
        )->build(
            $item
        );

        $this->assertNotNull(
            $evidence
        );

        $this->assertTrue(
            $evidence[
                'is_hosting'
            ]
        );

        $this->assertTrue(
            $evidence[
                'includes_security'
            ]
        );

        $this->assertTrue(
            $evidence[
                'includes_backups'
            ]
        );

        $this->assertSame(
            'Reforj',
            $evidence[
                'service_hint'
            ]
        );

        $this->assertSame(
            75.0,
            $evidence[
                'monthly_rate'
            ]
        );
    }
}
