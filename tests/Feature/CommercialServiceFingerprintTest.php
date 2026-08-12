<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use Tests\TestCase;

class CommercialServiceFingerprintTest extends TestCase
{
    public function test_hosting_wording_variants_collapse_to_same_service(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $first =
            $service->fingerprint(
                'Monthly Hosting, Security Updates & Backups'
            );

        $second =
            $service->fingerprint(
                'Hosting, Security Updates & Backups - July 2026'
            );

        $this->assertSame(
            'hosting',
            $first['service_type']
        );

        $this->assertSame(
            $first['fingerprint'],
            $second['fingerprint']
        );
    }

    public function test_distinct_hosted_properties_keep_distinct_fingerprints(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $fourJ =
            $service->fingerprint(
                'Monthly Hosting, Security Updates & Backups - 4J'
            );

        $reforj =
            $service->fingerprint(
                'Monthly Hosting, Security Updates & Backups - Reforj'
            );

        $this->assertNotSame(
            $fourJ['fingerprint'],
            $reforj['fingerprint']
        );

        $this->assertSame(
            '4J',
            $fourJ['service_hint']
        );

        $this->assertSame(
            'Reforj',
            $reforj['service_hint']
        );
    }

    public function test_month_and_year_do_not_create_false_service_variants(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $may =
            $service->fingerprint(
                'Monthly Hosting, Security Updates & Backups - May 2026'
            );

        $june =
            $service->fingerprint(
                'Monthly Hosting, Security Updates & Backups - June 2026'
            );

        $this->assertSame(
            $may['fingerprint'],
            $june['fingerprint']
        );
    }
}
