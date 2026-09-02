<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewService;
use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationService;
use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCommercialPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_separates_current_recent_stale_and_historical_recurring_evidence(): void
    {
        $hostingClient = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $hostingClient,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75,
            );
        }

        $domainClient = Client::factory()->create();

        foreach ([
            '2024-10-01',
            '2025-10-01',
        ] as $date) {
            $this->invoiceItem(
                client: $domainClient,
                date: $date,
                description: 'Domain Annual Renewal - example.com',
                amount: 120,
            );
        }

        $recentClient = Client::factory()->create();

        foreach ([
            '2026-04-30',
            '2026-05-31',
            '2026-06-30',
        ] as $date) {
            $this->invoiceItem(
                client: $recentClient,
                date: $date,
                description: 'Monthly Marketing Retainer',
                amount: 1000,
            );
        }

        $staleClient = Client::factory()->create();

        foreach ([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
        ] as $date) {
            $this->invoiceItem(
                client: $staleClient,
                date: $date,
                description: 'Paid Management - PPC',
                amount: 150,
            );
        }

        $historicalClient = Client::factory()->create();

        foreach ([
            '2025-01-31',
            '2025-02-28',
            '2025-03-31',
        ] as $date) {
            $this->invoiceItem(
                client: $historicalClient,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 50,
            );
        }

        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $this->assertSame(
            5,
            $position->serviceCandidateCount
        );

        $this->assertSame(
            5,
            $position->recurringCandidateCount
        );

        $this->assertSame(
            2,
            $position->currentRecurringCandidateCount
        );

        $this->assertSame(
            85.0,
            $position->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->recentlyObservedRecurringCandidateCount
        );

        $this->assertSame(
            1000.0,
            $position->recentlyObservedMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->staleRecurringCandidateCount
        );

        $this->assertSame(
            150.0,
            $position->staleMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->historicalRecurringCandidateCount
        );

        $this->assertSame(
            50.0,
            $position->historicalMonthlyEquivalent
        );

        $this->assertSame(
            'invoice_history_supported_not_reconciled',
            $position->evidenceStatus
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_position_breakdowns_reconcile_to_supported_current_value(): void
    {
        $firstClient = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $firstClient,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75,
            );
        }

        $secondClient = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $secondClient,
                date: $date,
                description: 'Social Media Retainer',
                amount: 600,
            );
        }

        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $serviceTypeTotal = round(
            collect(
                $position->byServiceType
            )->sum(
                'supported_current_monthly_equivalent'
            ),
            2
        );

        $clientTotal = round(
            collect(
                $position->byClient
            )->sum(
                'supported_current_monthly_equivalent'
            ),
            2
        );

        $this->assertSame(
            675.0,
            $position->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            $position->supportedCurrentMonthlyEquivalent,
            $serviceTypeTotal
        );

        $this->assertSame(
            $position->supportedCurrentMonthlyEquivalent,
            $clientTotal
        );

        $this->assertSame(
            2,
            count(
                $position->byServiceType
            )
        );

        $this->assertSame(
            2,
            count(
                $position->byClient
            )
        );
    }

    public function test_position_separates_canonical_observed_billing_from_unreconciled_evidence_without_inventing_contract_value(): void
    {
        $client =
            Client::factory()->create();

        foreach (
            [
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75,
            );
        }

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $before =
            app(
                CurrentCommercialPositionService::class
            )->position(
                $asOf
            );

        $this->assertSame(
            75.0,
            $before
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            0.0,
            $before
                ->canonicalCurrentObservedMonthlyEquivalent
        );

        $this->assertSame(
            75.0,
            $before
                ->unreconciledCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $before
                ->unreconciledCurrentRecurringCandidateCount
        );

        $this->assertNull(
            $before
                ->contractedMonthlyValue
        );

        $this->assertSame(
            'not_established',
            $before
                ->contractedValueStatus
        );

        $user =
            User::factory()->create();

        $assessment =
            app(
                ClientServiceCandidateAssessmentService::class
            )
                ->all(
                    $asOf
                )
                ->firstOrFail(
                    fn ($row) => $row
                        ->candidate
                        ->clientId
                            === $client->id
                        && $row
                            ->promotionReadiness
                            === 'ready_for_review'
                );

        app(
            ClientServiceReconciliationService::class
        )->confirm(
            clientId: $client->id,
            candidateFingerprint: $assessment
                ->candidate
                ->fingerprint,
            serviceName: 'Website Hosting',
            reviewedBy: $user->id,
            asOf: $asOf
        );

        $confirmed =
            app(
                CurrentCommercialPositionService::class
            )->position(
                $asOf
            );

        $this->assertSame(
            1,
            $confirmed
                ->canonicalActiveServiceCount
        );

        $this->assertSame(
            1,
            $confirmed
                ->canonicalCurrentRecurringServiceCount
        );

        $this->assertSame(
            75.0,
            $confirmed
                ->canonicalCurrentObservedMonthlyEquivalent
        );

        $this->assertSame(
            0.0,
            $confirmed
                ->unreconciledCurrentMonthlyEquivalent
        );

        $this->assertSame(
            75.0,
            $confirmed
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            0,
            $confirmed
                ->currentRecurringCandidateCount
        );

        $this->assertSame(
            0,
            $confirmed
                ->readyForReviewCount
        );

        $this->assertSame(
            'canonical_service_observed_billing',
            $confirmed
                ->evidenceStatus
        );

        $this->assertNull(
            $confirmed
                ->contractedMonthlyValue
        );

        /*
         * New unmatched evidence must not change canonical
         * observed recurring value before human attribution.
         */
        $this->invoiceItem(
            client: $client,
            date: '2026-09-30',
            description: 'Monthly Hosting, Security Updates & Backups',
            amount: 100,
        );

        $pending =
            app(
                CurrentCommercialPositionService::class
            )->position(
                CarbonImmutable::parse(
                    '2026-10-01'
                )
            );

        $this->assertSame(
            75.0,
            $pending
                ->canonicalCurrentObservedMonthlyEquivalent
        );

        $this->assertSame(
            0.0,
            $pending
                ->unreconciledCurrentMonthlyEquivalent
        );

        $this->assertSame(
            75.0,
            $pending
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $pending
                ->attributionReviewReadyCount
        );

        $attribution =
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->firstOrFail();

        app(
            ClientServiceAttributionReviewService::class
        )->approve(
            clientId: $client->id,
            candidateFingerprint: $attribution
                ->candidateFingerprint,
            reviewedBy: $user->id
        );

        $approved =
            app(
                CurrentCommercialPositionService::class
            )->position(
                CarbonImmutable::parse(
                    '2026-10-01'
                )
            );

        $this->assertSame(
            100.0,
            $approved
                ->canonicalCurrentObservedMonthlyEquivalent
        );

        $this->assertSame(
            100.0,
            $approved
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            0,
            $approved
                ->attributionReviewReadyCount
        );

        $this->assertNull(
            $approved
                ->contractedMonthlyValue
        );
    }

    public function test_source_item_atomic_composite_evidence_is_excluded_from_supported_current_value_until_decomposed(): void
    {
        $client =
            Client::factory()->create();

        foreach (
            [
                '2026-06-30',
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Monthly Consultancy / Support / Website Development / SEO / Content',
                amount: 4000,
            );
        }

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessments =
            app(
                ClientServiceCandidateAssessmentService::class
            )
                ->forClient(
                    $client,
                    $asOf
                )
                ->filter(
                    fn ($row) => $row
                        ->candidate
                        ->isCompositeCandidate()
                )
                ->values();

        /*
         * Composite evidence is deliberately source-item atomic.
         *
         * Even identical monthly descriptions remain independent
         * one-observation candidates because each source invoice
         * item may require a different human decomposition.
         */
        $this->assertCount(
            3,
            $assessments
        );

        $this->assertTrue(
            $assessments->every(
                fn ($assessment) => $assessment
                    ->candidate
                    ->evidenceCount
                    === 1
            )
        );

        $this->assertTrue(
            $assessments->every(
                fn ($assessment) => $assessment
                    ->candidate
                    ->cadence
                    === 'one_off'
            )
        );

        $this->assertTrue(
            $assessments->every(
                fn ($assessment) => ! $assessment
                    ->cadenceEstablished
            )
        );

        $this->assertTrue(
            $assessments->every(
                fn ($assessment) => $assessment
                    ->promotionReadiness
                    === 'needs_decomposition'
            )
        );

        $this->assertTrue(
            $assessments->every(
                fn ($assessment) => $assessment
                    ->currentMonthlyEquivalent
                    === null
            )
        );

        $this->assertSame(
            12000.0,
            round(
                (float) $assessments->sum(
                    fn ($assessment) => $assessment
                        ->candidate
                        ->signedObservedNet
                ),
                2
            )
        );

        $position =
            app(
                CurrentCommercialPositionService::class
            )->position(
                $asOf
            );

        /*
         * Composite source evidence remains outside ordinary
         * service truth regardless of repeated billing dates.
         */
        $this->assertSame(
            0,
            $position
                ->serviceCandidateCount
        );

        $this->assertSame(
            0,
            $position
                ->recurringCandidateCount
        );

        $this->assertSame(
            0,
            $position
                ->currentRecurringCandidateCount
        );

        $this->assertSame(
            0.0,
            $position
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            0.0,
            $position
                ->unreconciledCurrentMonthlyEquivalent
        );
    }

    public function test_project_and_pass_through_candidates_are_excluded_from_commercial_position(): void
    {
        $client = Client::factory()->create();

        $this->invoiceItem(
            client: $client,
            date: '2026-08-31',
            description: 'Website Design & Development',
            amount: 10000,
        );

        $this->invoiceItem(
            client: $client,
            date: '2026-08-31',
            description: 'Advertising Spend',
            amount: 5000,
        );

        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $this->assertSame(
            0,
            $position->serviceCandidateCount
        );

        $this->assertSame(
            0,
            $position->recurringCandidateCount
        );

        $this->assertSame(
            0.0,
            $position->supportedCurrentMonthlyEquivalent
        );
    }

    public function test_position_preserves_provenance_and_caveats(): void
    {
        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $this->assertSame(
            'accounting_invoice_items',
            $position->provenance['source']
        );

        $this->assertSame(
            'CommercialServiceFingerprint',
            $position->provenance['classification']
        );

        $this->assertNotEmpty(
            $position->caveats
        );

        $this->assertStringContainsString(
            'not MRR',
            implode(
                ' ',
                $position->caveats
            )
        );
    }

    private function invoiceItem(
        Client $client,
        string $date,
        string $description,
        float $amount
    ): AccountingInvoiceItem {
        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => (string) str()->uuid(),
            'invoice_date' => $date,
            'status' => 'paid',
        ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'description' => $description,

            'quantity' => 1,

            'unit_price' => $amount,

            'net_amount' => $amount,
        ]);
    }
}
