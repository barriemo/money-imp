<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CanonicalBillingObservationService;
use App\Domains\CommercialTruth\Services\CanonicalServiceObservedBillingService;
use App\Domains\CommercialTruth\Services\CompositeCommercialEvidenceFingerprint;
use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialEvidenceAllocation;
use App\Models\CommercialEvidenceAllocationSet;
use App\Models\CompositeCommercialReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CanonicalBillingObservationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_backed_monthly_bundle_establishes_canonical_observed_billing(): void
    {
        [$client, $service] =
            $this->service(
                'Bundled Digital Retainer'
            );

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $item =
                $this->invoiceItem(
                    client: $client,
                    service: null,
                    date: $date,
                    unitPrice: 500,
                    quantity: 3
                );

            $this->allocation(
                client: $client,
                item: $item,
                targets: [
                    [
                        $service,
                        150000,
                    ],
                ],
                kind: 'bundle'
            );
        }

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertNotNull(
            $truth
        );

        $this->assertSame(
            'monthly',
            $truth->cadence
        );

        $this->assertSame(
            1500.0,
            $truth->currentMonthlyEquivalent
        );

        $this->assertSame(
            500.0,
            $truth->latestObservedUnitPrice
        );

        $this->assertSame(
            4500.0,
            $truth->signedObservedNet
        );

        $this->assertSame(
            3,
            $truth->evidenceCount
        );
    }

    public function test_split_allocations_expose_only_each_services_allocated_value(): void
    {
        $client =
            Client::factory()->create();

        $development =
            $this->serviceForClient(
                $client,
                'Web Development'
            );

        $marketing =
            $this->serviceForClient(
                $client,
                'Marketing Support'
            );

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $item =
                $this->invoiceItem(
                    client: $client,
                    service: null,
                    date: $date,
                    unitPrice: 1500,
                    quantity: 1
                );

            $this->allocation(
                client: $client,
                item: $item,
                targets: [
                    [
                        $development,
                        100000,
                    ],
                    [
                        $marketing,
                        50000,
                    ],
                ],
                kind: 'split'
            );
        }

        $service =
            app(
                CanonicalServiceObservedBillingService::class
            );

        $developmentTruth =
            $service->forService(
                $development,
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $marketingTruth =
            $service->forService(
                $marketing,
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertSame(
            1000.0,
            $developmentTruth
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            500.0,
            $marketingTruth
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            3000.0,
            $developmentTruth
                ->signedObservedNet
        );

        $this->assertSame(
            1500.0,
            $marketingTruth
                ->signedObservedNet
        );
    }

    public function test_allocation_backed_written_off_evidence_is_not_canonical_billing(): void
    {
        [$client, $service] =
            $this->service(
                'Monthly Support'
            );

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $item =
                $this->invoiceItem(
                    client: $client,
                    service: null,
                    date: $date,
                    unitPrice: 1000
                );

            $this->allocation(
                client: $client,
                item: $item,
                targets: [
                    [
                        $service,
                        100000,
                    ],
                ],
                kind: 'bundle'
            );
        }

        $writtenOff =
            $this->invoiceItem(
                client: $client,
                service: null,
                date: '2026-09-01',
                unitPrice: 2500,
                status: 'written_off'
            );

        $this->allocation(
            client: $client,
            item: $writtenOff,
            targets: [
                [
                    $service,
                    250000,
                ],
            ],
            kind: 'bundle'
        );

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertSame(
            1000.0,
            $truth->currentMonthlyEquivalent
        );

        $this->assertSame(
            3,
            $truth->evidenceCount
        );

        $this->assertSame(
            3000.0,
            $truth->signedObservedNet
        );
    }

    public function test_changed_source_fingerprint_makes_prior_allocation_non_current(): void
    {
        [$client, $service] =
            $this->service(
                'Bundled Retainer'
            );

        $item =
            $this->invoiceItem(
                client: $client,
                service: null,
                date: '2026-08-31',
                unitPrice: 1500
            );

        $this->allocation(
            client: $client,
            item: $item,
            targets: [
                [
                    $service,
                    150000,
                ],
            ],
            kind: 'bundle'
        );

        $item->update([
            'description' => 'Materially changed source evidence',
        ]);

        $rows =
            app(
                CanonicalBillingObservationService::class
            )->all(
                clientServiceId: (string) $service->id
            );

        $this->assertCount(
            0,
            $rows
        );
    }

    public function test_corrupt_allocation_conservation_fails_loudly(): void
    {
        [$client, $service] =
            $this->service(
                'Bundled Retainer'
            );

        $item =
            $this->invoiceItem(
                client: $client,
                service: null,
                date: '2026-08-31',
                unitPrice: 1500
            );

        $set =
            $this->allocation(
                client: $client,
                item: $item,
                targets: [
                    [
                        $service,
                        150000,
                    ],
                ],
                kind: 'bundle'
            );

        $set->allocations
            ->first()
            ->update([
                'allocated_net_pence' => 140000,
            ]);

        $this->expectException(
            LogicException::class
        );

        app(
            CanonicalBillingObservationService::class
        )->all();
    }

    public function test_current_direct_and_allocation_overlap_fails_loudly(): void
    {
        [$client, $service] =
            $this->service(
                'Bundled Retainer'
            );

        $item =
            $this->invoiceItem(
                client: $client,
                service: null,
                date: '2026-08-31',
                unitPrice: 1500
            );

        $this->allocation(
            client: $client,
            item: $item,
            targets: [
                [
                    $service,
                    150000,
                ],
            ],
            kind: 'bundle'
        );

        /*
         * client_service_id is deliberately outside the composite
         * source evidence fingerprint. This therefore represents a
         * true concurrent attribution conflict rather than stale
         * source evidence.
         */
        $item->update([
            'client_service_id' => $service->id,
        ]);

        $this->expectException(
            LogicException::class
        );

        app(
            CanonicalBillingObservationService::class
        )->all();
    }

    public function test_allocation_preserves_source_quantity_for_existing_catch_up_normalisation(): void
    {
        [$client, $service] =
            $this->service(
                'Monthly Support'
            );

        foreach ([
            '2025-12-31',
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
            '2026-04-30',
        ] as $date) {
            $item =
                $this->invoiceItem(
                    client: $client,
                    service: null,
                    date: $date,
                    unitPrice: 50,
                    quantity: 1
                );

            $this->allocation(
                client: $client,
                item: $item,
                targets: [
                    [
                        $service,
                        5000,
                    ],
                ],
                kind: 'bundle'
            );
        }

        /*
         * Three months billed together after the gap.
         *
         * Allocation observation must remain:
         * qty 3 / unit £50 / net £150
         *
         * so the existing cadence engine recognises catch-up
         * billing and returns £50/month.
         */
        $catchUp =
            $this->invoiceItem(
                client: $client,
                service: null,
                date: '2026-07-31',
                unitPrice: 50,
                quantity: 3
            );

        $this->allocation(
            client: $client,
            item: $catchUp,
            targets: [
                [
                    $service,
                    15000,
                ],
            ],
            kind: 'bundle'
        );

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-08-01'
                )
            );

        $this->assertSame(
            'monthly',
            $truth->cadence
        );

        $this->assertSame(
            50.0,
            $truth->currentMonthlyEquivalent
        );
    }

    public function test_current_commercial_position_includes_allocation_backed_recurring_value_once(): void
    {
        [$client, $service] =
            $this->service(
                'Bundled Digital Retainer'
            );

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $item =
                $this->invoiceItem(
                    client: $client,
                    service: null,
                    date: $date,
                    unitPrice: 500,
                    quantity: 3
                );

            $this->allocation(
                client: $client,
                item: $item,
                targets: [
                    [
                        $service,
                        150000,
                    ],
                ],
                kind: 'bundle'
            );
        }

        $position =
            app(
                CurrentCommercialPositionService::class
            )->position(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertSame(
            1500.0,
            $position
                ->canonicalCurrentObservedMonthlyEquivalent
        );

        $this->assertSame(
            1500.0,
            $position
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            0.0,
            $position
                ->unreconciledCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position
                ->canonicalCurrentRecurringServiceCount
        );
    }

    public function test_existing_same_date_direct_catch_up_semantics_are_not_changed_by_stage_2b(): void
    {
        [$client, $service] =
            $this->service(
                'Monthly Hosting'
            );

        foreach ([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
        ] as $date) {
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: $date,
                unitPrice: 50
            );
        }

        /*
         * Multiple period-specific lines raised on the same
         * invoice date. Stage 2B must not sum these into a new
         * £150 monthly rate.
         */
        foreach ([
            'April',
            'May',
            'June',
        ] as $period) {
            $this->invoiceItem(
                client: $client,
                service: $service,
                date: '2026-04-30',
                unitPrice: 50,
                description: "Monthly Hosting {$period}"
            );
        }

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service,
                CarbonImmutable::parse(
                    '2026-05-01'
                )
            );

        $this->assertSame(
            'monthly',
            $truth->cadence
        );

        $this->assertSame(
            50.0,
            $truth->currentMonthlyEquivalent
        );
    }

    private function service(
        string $name
    ): array {
        $client =
            Client::factory()->create();

        return [
            $client,

            $this->serviceForClient(
                $client,
                $name
            ),
        ];
    }

    private function serviceForClient(
        Client $client,
        string $name
    ): ClientService {
        return ClientService::create([
            'client_id' => $client->id,

            'name' => $name,

            'type' => 'service',

            'status' => 'active',
        ]);
    }

    private function invoiceItem(
        Client $client,
        ?ClientService $service,
        string $date,
        float $unitPrice,
        float $quantity = 1,
        string $status = 'paid',
        string $description = 'Retainer, web dev, marketing and support'
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => (string) str()->uuid(),

                'invoice_date' => $date,

                'status' => $status,
            ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'client_service_id' => $service?->id,

            'description' => $description,

            'quantity' => $quantity,

            'unit_price' => $unitPrice,

            'net_amount' => $unitPrice
                * $quantity,
        ]);
    }

    /**
     * @param  array<int, array{0: ClientService, 1: int}>  $targets
     */
    private function allocation(
        Client $client,
        AccountingInvoiceItem $item,
        array $targets,
        string $kind
    ): CommercialEvidenceAllocationSet {
        /*
         * Rehydrate database defaults before fingerprinting.
         *
         * Production allocation goes through
         * CompositeCommercialAllocationService, which locks and
         * reloads the persisted source row before computing the
         * fingerprint.
         *
         * This test fixture must model that exact persisted state
         * rather than fingerprinting the just-created in-memory
         * model before SQLite defaults are rehydrated.
         */
        $item->refresh();
        $item->load('invoice');

        $fingerprint =
            app(
                CompositeCommercialEvidenceFingerprint::class
            )->forInvoiceItem(
                $item
            );

        $user =
            User::factory()->create();

        $decision =
            $kind === 'bundle'
                ? 'bundled_service'
                : 'requires_allocation';

        $review =
            CompositeCommercialReview::create([
                'accounting_invoice_item_id' => $item->id,

                'client_id' => $client->id,

                'candidate_fingerprint' => hash(
                    'sha256',
                    'candidate:'
                    .$item->id
                ),

                'evidence_fingerprint' => $fingerprint,

                'terminal_marker' => 'terminal',

                'decision' => $decision,

                'reviewed_by' => $user->id,

                'reviewed_at' => now(),

                'candidate_snapshot' => [],
            ]);

        $sourceNetPence =
            (int) round(
                ((float) $item->net_amount)
                * 100
            );

        $set =
            CommercialEvidenceAllocationSet::create([
                'composite_commercial_review_id' => $review->id,

                'accounting_invoice_item_id' => $item->id,

                'client_id' => $client->id,

                'evidence_fingerprint' => $fingerprint,

                'allocation_kind' => $kind,

                'source_net_pence' => $sourceNetPence,

                'allocated_by' => $user->id,

                'allocated_at' => now(),

                'allocation_snapshot' => [],
            ]);

        foreach ($targets as [
            $service,
            $allocatedNetPence,
        ]) {
            CommercialEvidenceAllocation::create([
                'allocation_set_id' => $set->id,

                'client_service_id' => $service->id,

                'allocated_net_pence' => $allocatedNetPence,
            ]);
        }

        return $set->load(
            'allocations'
        );
    }
}
