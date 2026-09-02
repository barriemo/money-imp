<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use Tests\TestCase;

class CommercialServiceFingerprintTest extends TestCase
{
    public function test_existing_mml_retainer_identity_remains_stable_and_is_not_composite(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'Monthly Consultancy / Implementations / Support (retainer)'
            );

        $this->assertSame(
            'retainer',
            $result['service_type']
        );

        $this->assertSame(
            'service_candidate',
            $result['commercial_treatment']
        );

        $this->assertSame(
            [
                'retainer',
                'support',
            ],
            $result['commercial_components']
        );

        $this->assertSame(
            '62333b362d3cd311ee58d20257f4ef9c5b7e517b7a955b7bea0f1349b015e461',
            $result['fingerprint']
        );
    }

    public function test_mml_multi_activity_line_is_composite_not_wholesale_seo(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'Monthly Consultancy / Implementations / Support (retainer) / Website Development / App Development / SEO / Content .'
            );

        $this->assertSame(
            'composite',
            $result['service_type']
        );

        $this->assertSame(
            'composite_candidate',
            $result['commercial_treatment']
        );

        $this->assertSame(
            [
                'retainer',
                'support',
                'development',
                'seo',
                'content',
            ],
            $result['commercial_components']
        );

        $this->assertSame(
            95,
            $result['classification_confidence']
        );
    }

    public function test_two_component_service_wording_does_not_trigger_composite_boundary(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'Monthly SEO & Content'
            );

        $this->assertSame(
            'seo',
            $result['service_type']
        );

        $this->assertSame(
            'service_candidate',
            $result['commercial_treatment']
        );

        $this->assertSame(
            [
                'seo',
                'content',
            ],
            $result['commercial_components']
        );
    }

    public function test_managed_service_bundle_can_be_detected_as_composite(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'Monthly Hosting / Microsoft 365 / Domain Renewal - example.com'
            );

        $this->assertSame(
            'composite',
            $result['service_type']
        );

        $this->assertSame(
            'composite_candidate',
            $result['commercial_treatment']
        );

        $this->assertEqualsCanonicalizing(
            [
                'hosting',
                'microsoft365',
                'domain',
            ],
            $result['commercial_components']
        );
    }

    public function test_media_spend_mixed_with_management_and_seo_is_composite(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'PPC Management / Advertising Spend / SEO'
            );

        $this->assertSame(
            'composite',
            $result['service_type']
        );

        $this->assertSame(
            'composite_candidate',
            $result['commercial_treatment']
        );

        $this->assertEqualsCanonicalizing(
            [
                'media_spend',
                'ppc_management',
                'seo',
            ],
            $result['commercial_components']
        );
    }

    public function test_content_delivery_network_is_not_mistaken_for_content_marketing(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'Content Delivery Network'
            );

        $this->assertSame(
            'cdn',
            $result['service_type']
        );

        $this->assertSame(
            'managed_service_candidate',
            $result['commercial_treatment']
        );

        $this->assertSame(
            [
                'cdn',
            ],
            $result['commercial_components']
        );
    }

    public function test_separate_content_activity_can_coexist_with_cdn_in_composite_evidence(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'Content Delivery Network / SEO / Content / Support'
            );

        $this->assertSame(
            'composite',
            $result['service_type']
        );

        $this->assertEqualsCanonicalizing(
            [
                'cdn',
                'seo',
                'content',
                'support',
            ],
            $result['commercial_components']
        );
    }

    public function test_real_burtys_package_preserves_all_detectable_component_families(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result =
            $service->fingerprint(
                'F&F Package - site dev & annual hosting, inc domain reg & google workspace annual email.'
            );

        $this->assertSame(
            'composite',
            $result['service_type']
        );

        $this->assertSame(
            'composite_candidate',
            $result['commercial_treatment']
        );

        $this->assertEqualsCanonicalizing(
            [
                'development',
                'hosting',
                'google_workspace',
                'domain',
            ],
            $result['commercial_components']
        );
    }

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

    public function test_annual_domain_description_remains_a_managed_service_candidate(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result = $service->fingerprint(
            'Domain Annual Renewal - example.com'
        );

        $this->assertSame(
            'domain',
            $result['service_type']
        );

        $this->assertSame(
            'managed_service_candidate',
            $result['commercial_treatment']
        );

        $this->assertSame(
            90,
            $result['classification_confidence']
        );
    }

    public function test_billing_period_suffixes_do_not_create_distinct_service_identities(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $november = $service->fingerprint(
            'Monthly Email Licenses Office 365 - Nov25'
        );

        $december = $service->fingerprint(
            'Monthly Email Licenses Office 365 - Dec25'
        );

        $this->assertNull(
            $november['service_hint']
        );

        $this->assertNull(
            $december['service_hint']
        );

        $this->assertSame(
            $november['fingerprint'],
            $december['fingerprint']
        );
    }

    public function test_period_labelled_service_does_not_silently_merge_with_no_hint_history(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $historic = $service->fingerprint(
            'Monthly Email Licenses Office 365'
        );

        $periodLabelled = $service->fingerprint(
            'Monthly Email Licenses Office 365 - Nov25'
        );

        $this->assertNotSame(
            $historic['fingerprint'],
            $periodLabelled['fingerprint']
        );
    }

    public function test_named_service_hint_keeps_identity_but_loses_trailing_period_noise(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $march = $service->fingerprint(
            'Monthly Hosting - Ruby Online March26'
        );

        $shortYear = $service->fingerprint(
            'Monthly Hosting - Ruby Online 26'
        );

        $this->assertSame(
            'Ruby Online',
            $march['service_hint']
        );

        $this->assertSame(
            'Ruby Online',
            $shortYear['service_hint']
        );

        $this->assertSame(
            $march['fingerprint'],
            $shortYear['fingerprint']
        );
    }

    public function test_real_hosting_property_hints_remain_distinct(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $usa = $service->fingerprint(
            'Monthly Hosting - USA'
        );

        $uk = $service->fingerprint(
            'Monthly Hosting - UK'
        );

        $this->assertSame(
            'USA',
            $usa['service_hint']
        );

        $this->assertSame(
            'UK',
            $uk['service_hint']
        );

        $this->assertNotSame(
            $usa['fingerprint'],
            $uk['fingerprint']
        );
    }

    public function test_purchase_order_number_is_not_a_service_identity(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $first = $service->fingerprint(
            "Annual Hosting of Corporate Website\n"
            .'(Hosting, Security Updates & Backups) - PO-4011'
        );

        $second = $service->fingerprint(
            "Annual Hosting of Corporate Website\n"
            .'(Hosting, Security Updates & Backups) - PO-4728'
        );

        $this->assertNull(
            $first['service_hint']
        );

        $this->assertNull(
            $second['service_hint']
        );

        $this->assertSame(
            $first['fingerprint'],
            $second['fingerprint']
        );
    }

    public function test_text_after_purchase_order_number_can_remain_a_service_hint(): void
    {
        $service = app(
            CommercialServiceFingerprint::class
        );

        $result = $service->fingerprint(
            'Annual Hosting of Corporate Website - PO-4728 Legacy'
        );

        $this->assertSame(
            'Legacy',
            $result['service_hint']
        );
    }
}
