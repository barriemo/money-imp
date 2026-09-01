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

    public function test_advertising_spend_is_not_treated_as_ppc_management(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result = $service->fingerprint(
            'PPC - Advertising spend Budget - Google'
        );

        $this->assertSame(
            'media_spend',
            $result['service_type']
        );

        $this->assertSame(
            'pass_through_candidate',
            $result['commercial_treatment']
        );

        $this->assertSame(
            95,
            $result['classification_confidence']
        );
    }

    public function test_ppc_management_remains_service_candidate(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result = $service->fingerprint(
            'Paid Management - PPC'
        );

        $this->assertSame(
            'ppc_management',
            $result['service_type']
        );

        $this->assertSame(
            'service_candidate',
            $result['commercial_treatment']
        );
    }

    public function test_development_work_is_only_a_project_candidate(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result = $service->fingerprint(
            'V7 Rolex Development Work'
        );

        $this->assertSame(
            'development',
            $result['service_type']
        );

        $this->assertSame(
            'project_candidate',
            $result['commercial_treatment']
        );
    }

    public function test_common_managed_service_lines_are_classified(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $examples = [
            'Monthly Email Licenses Office 365' => 'microsoft365',
            'Google Workspace Business Annual Renewal' => 'google_workspace',
            'Mailchimp' => 'email_platform',
            'Nitro CDN' => 'cdn',
            'Domain Name Annual Renewal' => 'domain',
        ];

        foreach ($examples as $description => $expected) {
            $result = $service->fingerprint(
                $description
            );

            $this->assertSame(
                $expected,
                $result['service_type'],
                $description
            );

            $this->assertSame(
                'managed_service_candidate',
                $result['commercial_treatment'],
                $description
            );
        }
    }
}
