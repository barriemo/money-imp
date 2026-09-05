<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Domains\BusinessBrain\Evidence\ClientPaymentEvidenceSummary;
use App\Domains\BusinessBrain\Evidence\ClientPaymentEvidenceSummaryService;
use App\Domains\BusinessBrain\RevenueTruth\CanonicalClientRevenueObservation;
use App\Domains\BusinessBrain\RevenueTruth\CanonicalClientRevenueObservationService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class CanonicalClientRevenueObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_client_revenue_observation_preserves_only_bounded_factual_inputs(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Canonical Revenue Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-CANONICAL-001',

            'status' => 'overdue',

            'invoice_date' => '2026-08-01',

            'due_date' => '2026-08-31',

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 200,

            'outstanding_amount' => 1000,
        ]);

        $service =
            $this->serviceFor(
                client: $client,

                delivery: $this->delivery(
                    $client,
                    workLogCount: 3,
                    commercialValue: 900.00,
                    uninvoicedCommercialValue: 300.00
                ),

                payment: $this->payment(
                    $client,
                    approvedCount: 2,
                    approvedValue: 200.00
                )
            );

        $asOf =
            CarbonImmutable::parse(
                '2026-09-05 15:00:00'
            );

        $observation =
            $service->forClient(
                $client,
                $asOf
            );

        $this->assertTrue(
            Str::isUuid(
                $observation->clientId
            )
        );

        $this->assertSame(
            (string) $client->id,
            $observation->clientId
        );

        $this->assertSame(
            'Canonical Revenue Client',
            $observation->clientName
        );

        $this->assertSame(
            1,
            $observation->accountingInvoiceCount
        );

        $this->assertSame(
            1200.0,
            $observation->accountingGrossInvoicedAmount
        );

        $this->assertSame(
            200.0,
            $observation->accountingReportedPaidAmount
        );

        $this->assertSame(
            1000.0,
            $observation->accountingReportedOutstandingAmount
        );

        $this->assertSame(
            3,
            $observation->recordedWorkLogCount
        );

        $this->assertSame(
            900.0,
            $observation->recordedWorkCommercialValue
        );

        $this->assertSame(
            300.0,
            $observation->recordedUninvoicedWorkCommercialValue
        );

        $this->assertSame(
            2,
            $observation->approvedOrImportedPaymentAllocationCount
        );

        $this->assertSame(
            200.0,
            $observation->approvedOrImportedPaymentAllocationValue
        );

        $this->assertSame(
            $asOf,
            $observation->observedAt
        );

        $this->assertSame(
            CanonicalClientRevenueObservation::TRUTH_BOUNDARY,
            $observation->truthBoundary
        );
    }

    public function test_exact_client_observation_does_not_leak_other_client_invoices(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Exact Client',
            ]);

        $other =
            Client::factory()->create([
                'name' => 'Other Client',
            ]);

        $this->invoice(
            $client,
            'INV-EXACT',
            1200
        );

        $this->invoice(
            $other,
            'INV-OTHER',
            9999
        );

        $service =
            $this->serviceFor(
                client: $client,

                delivery: $this->delivery(
                    $client
                ),

                payment: $this->payment(
                    $client
                )
            );

        $observation =
            $service->forClient(
                $client
            );

        $this->assertSame(
            1,
            $observation->accountingInvoiceCount
        );

        $this->assertSame(
            1200.0,
            $observation->accountingGrossInvoicedAmount
        );
    }

    public function test_mismatched_delivery_subject_fails_closed(): void
    {
        $client =
            Client::factory()->create();

        $delivery =
            $this->delivery(
                $client
            );

        $delivery->clientId =
            '00000000-0000-4000-8000-000000000099';

        $service =
            $this->serviceFor(
                client: $client,
                delivery: $delivery,
                payment: $this->payment(
                    $client
                )
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Canonical client revenue observation received delivery evidence for a different client.'
        );

        $service->forClient(
            $client
        );
    }

    public function test_mismatched_payment_subject_fails_closed(): void
    {
        $client =
            Client::factory()->create();

        $payment =
            $this->payment(
                $client
            );

        $payment->clientId =
            '00000000-0000-4000-8000-000000000099';

        $service =
            $this->serviceFor(
                client: $client,
                delivery: $this->delivery(
                    $client
                ),
                payment: $payment
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Canonical client revenue observation received payment evidence for a different client.'
        );

        $service->forClient(
            $client
        );
    }

    public function test_canonical_observation_exposes_no_confidence_priority_recommendation_or_execution_authority(): void
    {
        $reflection =
            new ReflectionClass(
                CanonicalClientRevenueObservation::class
            );

        foreach (
            [
                'commercialConfidence',
                'averageCommercialConfidence',
                'paymentEvidenceConfidence',
                'priority',
                'priorityInvoices',
                'highestPriority',
                'recommendation',
                'recommendedAction',
                'recoverableMonthly',
                'recoverableAnnual',
                'estimatedRecoveryValue',
                'billingObligation',
                'contractualAmount',
                'collectionAction',
                'chaseAction',
                'execution',
                'executedAt',
                'outcomeId',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_truth_boundary_preserves_source_observation_vs_commercial_authority(): void
    {
        $boundary =
            strtolower(
                CanonicalClientRevenueObservation::TRUTH_BOUNDARY
            );

        $this->assertStringContainsString(
            'accounting-reported outstanding is not verified collectible revenue',
            $boundary
        );

        $this->assertStringContainsString(
            'not proof of contractual entitlement or recoverability',
            $boundary
        );

        $this->assertStringContainsString(
            'not proof that all payment truth is complete',
            $boundary
        );

        $this->assertStringContainsString(
            'does not establish a billing obligation',
            $boundary
        );

        $this->assertStringContainsString(
            'zero observed values do not prove that no unrecorded revenue entitlement exists',
            $boundary
        );
    }

    public function test_zero_observation_remains_bounded_and_does_not_become_no_revenue_entitlement(): void
    {
        $client =
            Client::factory()->create();

        $service =
            $this->serviceFor(
                client: $client,
                delivery: $this->delivery(
                    $client
                ),
                payment: $this->payment(
                    $client
                )
            );

        $observation =
            $service->forClient(
                $client
            );

        $this->assertSame(
            0,
            $observation->accountingInvoiceCount
        );

        $this->assertSame(
            0.0,
            $observation->accountingGrossInvoicedAmount
        );

        $this->assertSame(
            0.0,
            $observation->accountingReportedOutstandingAmount
        );

        $this->assertSame(
            0,
            $observation->recordedWorkLogCount
        );

        $this->assertSame(
            0.0,
            $observation->approvedOrImportedPaymentAllocationValue
        );

        $this->assertStringContainsString(
            'zero observed values do not prove that no unrecorded revenue entitlement exists',
            strtolower(
                $observation->truthBoundary
            )
        );
    }

    public function test_canonical_service_does_not_depend_on_heuristic_or_recovery_authority(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/BusinessBrain/RevenueTruth/CanonicalClientRevenueObservationService.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'RevenueTruthService',
                'RevenueTruthSummaryService',
                'CommercialGapDetector',
                'ReceivableRealityService',
                'RevenueRecoveryEngine',
                'RevenueRecommendationEngine',
                'RevenueRecommendation',
                'commercialConfidence',
                'averageCommercialConfidence',
                'priorityInvoices',
                'highestPriority',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function serviceFor(
        Client $client,
        DeliveryTruth $delivery,
        ClientPaymentEvidenceSummary $payment,
    ): CanonicalClientRevenueObservationService {
        $deliveryService =
            Mockery::mock(
                DeliveryTruthService::class
            );

        $deliveryService
            ->shouldReceive(
                'forClient'
            )
            ->once()
            ->with(
                $client
            )
            ->andReturn(
                $delivery
            );

        $paymentService =
            Mockery::mock(
                ClientPaymentEvidenceSummaryService::class
            );

        $paymentService
            ->shouldReceive(
                'forClient'
            )
            ->once()
            ->with(
                $client
            )
            ->andReturn(
                $payment
            );

        return new CanonicalClientRevenueObservationService(
            $deliveryService,
            $paymentService
        );
    }

    private function delivery(
        Client $client,
        int $workLogCount = 0,
        float $commercialValue = 0.0,
        float $uninvoicedCommercialValue = 0.0,
    ): DeliveryTruth {
        return new DeliveryTruth(
            clientId: (string) $client->id,

            client: $client->name,

            workLogCount: $workLogCount,

            invoicedWorkLogCount: 0,

            uninvoicedWorkLogCount: $workLogCount,

            commercialValue: $commercialValue,

            invoicedCommercialValue: $commercialValue
                - $uninvoicedCommercialValue,

            uninvoicedCommercialValue: $uninvoicedCommercialValue,

            invoiceLinkageConfidence: 0
        );
    }

    private function payment(
        Client $client,
        int $approvedCount = 0,
        float $approvedValue = 0.0,
    ): ClientPaymentEvidenceSummary {
        return new ClientPaymentEvidenceSummary(
            clientId: (string) $client->id,

            client: $client->name,

            invoiceCount: 0,

            paidInvoiceCount: 0,

            approvedPaymentAllocationCount: $approvedCount,

            suggestedPaymentAllocationCount: 0,

            paidInvoicesWithoutApprovedEvidence: 0,

            approvedPaymentValue: $approvedValue,

            confidence: 100
        );
    }

    private function invoice(
        Client $client,
        string $number,
        float $gross
    ): AccountingInvoice {
        return AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => $number,

            'status' => 'outstanding',

            'invoice_date' => '2026-08-01',

            'due_date' => '2026-08-31',

            'currency' => 'GBP',

            'net_amount' => $gross,

            'tax_amount' => 0,

            'gross_amount' => $gross,

            'paid_amount' => 0,

            'outstanding_amount' => $gross,
        ]);
    }
}
