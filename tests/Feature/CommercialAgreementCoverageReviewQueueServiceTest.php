<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CommercialAgreementAssertionService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageReviewQueueService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageReviewService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use App\Models\CommercialAgreementEvidence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAgreementCoverageReviewQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_contains_only_unresolved_effective_active_services_and_reads_are_pure(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Queue Client',
            ]);

        $reviewer =
            User::factory()->create();

        $resolved =
            $this->service(
                client: $client,

                name: 'Resolved Service'
            );

        $unresolved =
            $this->service(
                client: $client,

                name: 'Unresolved Service'
            );

        $this->service(
            client: $client,

            name: 'Historical Service',

            status: 'historical'
        );

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmNoCurrentContract(
            clientServiceId: $resolved->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Explicitly resolved.'
        );

        $before = [
            'reviews' => CommercialAgreementCoverageReview::count(),

            'agreements' => CommercialAgreement::count(),

            'evidence' => CommercialAgreementEvidence::count(),
        ];

        $queue =
            app(
                CommercialAgreementCoverageReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            );

        $after = [
            'reviews' => CommercialAgreementCoverageReview::count(),

            'agreements' => CommercialAgreement::count(),

            'evidence' => CommercialAgreementEvidence::count(),
        ];

        $this->assertCount(
            1,
            $queue
        );

        $this->assertSame(
            $unresolved->id,
            $queue->first()
                ->clientServiceId
        );

        $this->assertSame(
            'unreviewed',
            $queue->first()
                ->coverageState
        );

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_queue_prioritises_stale_review_then_deferred_then_unknown_evidence_then_routine_current_billing(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Priority Client',
            ]);

        $reviewer =
            User::factory()->create();

        $stale =
            $this->service(
                client: $client,

                name: 'A Stale Contract'
            );

        $deferred =
            $this->service(
                client: $client,

                name: 'B Needs Evidence'
            );

        $unknown =
            $this->service(
                client: $client,

                name: 'C No Billing Evidence'
            );

        $routine =
            $this->service(
                client: $client,

                name: 'D Current Monthly Billing'
            );

        $agreements =
            app(
                CommercialAgreementAssertionService::class
            );

        $first =
            $agreements->confirm(
                clientServiceId: $stale->id,

                cadence: 'monthly',

                contractedAmountPence: 50000,

                effectiveFrom: CarbonImmutable::parse(
                    '2026-01-01'
                ),

                reviewedBy: $reviewer->id,

                source: 'signed_agreement',

                reason: 'Original terms.'
            );

        app(
            CommercialAgreementCoverageReviewService::class
        )->confirmTerms(
            clientServiceId: $stale->id,

            commercialAgreementId: $first->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'September terms reviewed.'
        );

        $agreements->supersede(
            commercialAgreementId: $first->id,

            cadence: 'monthly',

            contractedAmountPence: 75000,

            effectiveFrom: CarbonImmutable::parse(
                '2026-10-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'email',

            reason: 'October terms.'
        );

        app(
            CommercialAgreementCoverageReviewService::class
        )->defer(
            clientServiceId: $deferred->id,

            effectiveFrom: CarbonImmutable::parse(
                '2026-09-01'
            ),

            reviewedBy: $reviewer->id,

            source: 'owner_review',

            reason: 'Contract evidence still required.'
        );

        foreach (
            [
                '2026-07-31',
                '2026-08-31',
                '2026-09-30',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,

                service: $routine,

                date: $date,

                amount: 75
            );
        }

        $queue =
            app(
                CommercialAgreementCoverageReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-10-02'
                )
            );

        $this->assertSame(
            [
                $stale->id,
                $deferred->id,
                $unknown->id,
                $routine->id,
            ],
            $queue
                ->pluck(
                    'clientServiceId'
                )
                ->all()
        );

        $this->assertSame(
            [
                100,
                95,
                90,
                70,
            ],
            $queue
                ->pluck(
                    'priority'
                )
                ->all()
        );

        $this->assertSame(
            'stale_terminal_review',
            $queue[0]
                ->coverageState
        );

        $this->assertSame(
            'needs_more_evidence',
            $queue[1]
                ->coverageState
        );

        $this->assertSame(
            'no_observed_billing',
            $queue[2]
                ->observedBillingState
        );

        $this->assertSame(
            'current_recurring_observed',
            $queue[3]
                ->observedBillingState
        );
    }

    public function test_queue_exposes_observed_billing_without_promoting_it_to_contract_truth(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Observed Client',
            ]);

        $service =
            $this->service(
                client: $client,

                name: 'Website Hosting'
            );

        foreach (
            [
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,

                service: $service,

                date: $date,

                amount: 75
            );
        }

        $candidate =
            app(
                CommercialAgreementCoverageReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-03'
                )
            )
                ->firstOrFail();

        $this->assertSame(
            'current_recurring_observed',
            $candidate
                ->observedBillingState
        );

        $this->assertSame(
            3,
            $candidate
                ->observedEvidenceCount
        );

        $this->assertSame(
            'monthly',
            $candidate
                ->observedCadence
        );

        $this->assertSame(
            'current',
            $candidate
                ->observedFreshness
        );

        $this->assertSame(
            75.0,
            $candidate
                ->observedCurrentMonthlyEquivalent
        );

        $this->assertNull(
            $candidate
                ->currentAgreementId
        );

        $this->assertSame(
            [
                'no_current_contract',
                'needs_more_evidence',
            ],
            $candidate
                ->availableDecisions
        );

        $this->assertSame(
            0,
            CommercialAgreement::count()
        );

        $this->assertSame(
            0,
            CommercialAgreementCoverageReview::count()
        );
    }

    private function service(
        Client $client,
        string $name,
        string $status = 'active'
    ): ClientService {
        return ClientService::create([
            'client_id' => $client->id,

            'name' => $name,

            'type' => 'service',

            'status' => $status,
        ]);
    }

    private function invoiceItem(
        Client $client,
        ClientService $service,
        string $date,
        float $amount
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => (string) str()->uuid(),

                'invoice_date' => $date,

                'status' => 'paid',
            ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'client_service_id' => $service->id,

            'description' => $service->name,

            'quantity' => 1,

            'unit_price' => $amount,

            'net_amount' => $amount,
        ]);
    }
}
