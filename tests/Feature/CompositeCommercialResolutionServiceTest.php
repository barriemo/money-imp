<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use App\Domains\CommercialTruth\Services\CanonicalServiceObservedBillingService;
use App\Domains\CommercialTruth\Services\CompositeCommercialAllocationService;
use App\Domains\CommercialTruth\Services\CompositeCommercialEvidenceReviewQueueService;
use App\Domains\CommercialTruth\Services\CompositeCommercialResolutionQueueService;
use App\Domains\CommercialTruth\Services\CompositeCommercialResolutionService;
use App\Domains\CommercialTruth\Services\CompositeCommercialReviewService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialEvidenceAllocationSet;
use App\Models\CompositeCommercialResolutionTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompositeCommercialResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_terminal_review_moves_from_structural_queue_to_pending_resolution_queue(): void
    {
        [
            ,
            ,
            $review,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $this->assertCount(
            0,
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            )
        );

        $pending =
            app(
                CompositeCommercialResolutionQueueService::class
            )->ready();

        $this->assertCount(
            1,
            $pending
        );

        $this->assertSame(
            (string) $review->id,
            (string) $pending
                ->first()
                ->id
        );
    }

    public function test_allocated_review_leaves_pending_resolution_queue(): void
    {
        [
            $client,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $service =
            $this->service(
                $client,
                'Existing Retainer',
                'historical'
            );

        app(
            CompositeCommercialAllocationService::class
        )->allocate(
            compositeReviewId: (string) $review->id,

            allocations: [
                [
                    'client_service_id' => (string) $service->id,

                    'allocated_net_pence' => 150000,
                ],
            ],

            allocatedBy: $user->id
        );

        $this->assertCount(
            0,
            app(
                CompositeCommercialResolutionQueueService::class
            )->ready()
        );
    }

    public function test_stale_terminal_review_leaves_resolution_queue_and_changed_source_returns_to_structural_review(): void
    {
        [
            ,
            $item,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $item->update([
            'net_amount' => 1600,
        ]);

        $this->assertCount(
            0,
            app(
                CompositeCommercialResolutionQueueService::class
            )->ready()
        );

        $this->assertCount(
            1,
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            )
        );
    }

    public function test_bundle_can_create_historical_target_and_allocate_exact_source_without_direct_attribution(): void
    {
        [
            ,
            $item,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            decision: 'bundled_service',
            date: '2025-01-27'
        );

        $set =
            app(
                CompositeCommercialResolutionService::class
            )->resolve(
                compositeReviewId: (string) $review->id,

                targets: [
                    [
                        'service_name' => 'Bundled Digital Retainer',

                        'service_status' => 'historical',

                        'allocated_net_pence' => 150000,
                    ],
                ],

                resolvedBy: $user->id,

                reason: 'Historical bundled commercial service.'
            );

        $target =
            CompositeCommercialResolutionTarget::firstOrFail();

        $service =
            ClientService::findOrFail(
                $target->client_service_id
            );

        $this->assertSame(
            'historical',
            $service->status
        );

        $this->assertSame(
            'created',
            $target->target_action
        );

        $this->assertNull(
            $target
                ->previous_service_status
        );

        $this->assertSame(
            150000,
            $target->allocated_net_pence
        );

        $this->assertSame(
            (string) $set->id,
            (string) $target
                ->allocation_set_id
        );

        $this->assertNull(
            $item->fresh()
                ->client_service_id
        );

        $this->assertCount(
            0,
            app(
                CompositeCommercialResolutionQueueService::class
            )->ready()
        );
    }

    public function test_bundle_can_use_existing_historical_target_without_changing_its_status(): void
    {
        [
            $client,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $service =
            $this->service(
                $client,
                'Historic Bundled Retainer',
                'historical'
            );

        app(
            CompositeCommercialResolutionService::class
        )->resolve(
            compositeReviewId: (string) $review->id,

            targets: [
                [
                    'client_service_id' => (string) $service->id,

                    'service_status' => 'historical',

                    'allocated_net_pence' => 150000,
                ],
            ],

            resolvedBy: $user->id
        );

        $target =
            CompositeCommercialResolutionTarget::firstOrFail();

        $this->assertSame(
            'existing',
            $target->target_action
        );

        $this->assertSame(
            'historical',
            $target
                ->previous_service_status
        );

        $this->assertSame(
            'historical',
            $target
                ->resulting_service_status
        );

        $this->assertSame(
            'historical',
            $service->fresh()
                ->status
        );
    }

    public function test_existing_historical_service_can_be_explicitly_reactivated_and_new_allocation_updates_current_observed_billing(): void
    {
        $client =
            Client::factory()->create();

        $service =
            $this->service(
                $client,
                'Monthly Consultancy / Implementations / Support (retainer)',
                'historical'
            );

        foreach ([
            '2026-04-30',
            '2026-05-31',
            '2026-06-30',
        ] as $date) {
            $this->directInvoiceItem(
                client: $client,
                service: $service,
                date: $date,
                amount: 1000
            );
        }

        [
            ,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidenceForClient(
            client: $client,
            decision: 'bundled_service',
            date: '2026-07-31',
            netAmount: 4000,
            quantity: 1,
            description: 'Monthly Consultancy / Implementations / Support (retainer) / Website Development / App Development / SEO / Content .'
        );

        app(
            CompositeCommercialResolutionService::class
        )->resolve(
            compositeReviewId: (string) $review->id,

            targets: [
                [
                    'client_service_id' => (string) $service->id,

                    'service_status' => 'active',

                    'allocated_net_pence' => 400000,
                ],
            ],

            resolvedBy: $user->id,

            reason: 'Current composite evidence confirms this existing retainer is active.'
        );

        $target =
            CompositeCommercialResolutionTarget::firstOrFail();

        $this->assertSame(
            'reactivated',
            $target->target_action
        );

        $this->assertSame(
            'historical',
            $target
                ->previous_service_status
        );

        $this->assertSame(
            'active',
            $service->fresh()
                ->status
        );

        $truth =
            app(
                CanonicalServiceObservedBillingService::class
            )->forService(
                $service->fresh(),
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
            4000.0,
            $truth
                ->currentMonthlyEquivalent
        );
    }

    public function test_split_resolution_can_mix_existing_and_new_targets_and_preserve_exact_allocation(): void
    {
        [
            $client,
            $item,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'requires_allocation'
        );

        $existing =
            $this->service(
                $client,
                'Development',
                'active'
            );

        $set =
            app(
                CompositeCommercialResolutionService::class
            )->resolve(
                compositeReviewId: (string) $review->id,

                targets: [
                    [
                        'client_service_id' => (string) $existing->id,

                        'service_status' => 'active',

                        'allocated_net_pence' => 100000,
                    ],
                    [
                        'service_name' => 'Marketing Support',

                        'service_status' => 'historical',

                        'allocated_net_pence' => 50000,
                    ],
                ],

                resolvedBy: $user->id
            );

        $this->assertSame(
            'split',
            $set->allocation_kind
        );

        $this->assertSame(
            150000,
            $set->allocations
                ->sum(
                    'allocated_net_pence'
                )
        );

        $this->assertSame(
            2,
            CompositeCommercialResolutionTarget::count()
        );

        $this->assertSame(
            [
                'created',
                'existing',
            ],
            CompositeCommercialResolutionTarget::query()
                ->pluck('target_action')
                ->sort()
                ->values()
                ->all()
        );

        $this->assertNull(
            $item->fresh()
                ->client_service_id
        );
    }

    public function test_cross_client_existing_target_is_rejected_without_resolution_or_allocation(): void
    {
        [
            ,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $foreignClient =
            Client::factory()->create();

        $foreignService =
            $this->service(
                $foreignClient,
                'Foreign Service',
                'active'
            );

        $caught =
            false;

        try {
            app(
                CompositeCommercialResolutionService::class
            )->resolve(
                compositeReviewId: (string) $review->id,

                targets: [
                    [
                        'client_service_id' => (string) $foreignService->id,

                        'service_status' => 'active',

                        'allocated_net_pence' => 150000,
                    ],
                ],

                resolvedBy: $user->id
            );
        } catch (ValidationException) {
            $caught =
                true;
        }

        $this->assertTrue(
            $caught
        );

        $this->assertSame(
            0,
            CommercialEvidenceAllocationSet::count()
        );

        $this->assertSame(
            0,
            CompositeCommercialResolutionTarget::count()
        );
    }

    public function test_active_existing_service_cannot_be_silently_demoted_to_historical(): void
    {
        [
            $client,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $service =
            $this->service(
                $client,
                'Active Retainer',
                'active'
            );

        $caught =
            false;

        try {
            app(
                CompositeCommercialResolutionService::class
            )->resolve(
                compositeReviewId: (string) $review->id,

                targets: [
                    [
                        'client_service_id' => (string) $service->id,

                        'service_status' => 'historical',

                        'allocated_net_pence' => 150000,
                    ],
                ],

                resolvedBy: $user->id
            );
        } catch (ValidationException) {
            $caught =
                true;
        }

        $this->assertTrue(
            $caught
        );

        $this->assertSame(
            'active',
            $service->fresh()
                ->status
        );

        $this->assertSame(
            0,
            CommercialEvidenceAllocationSet::count()
        );
    }

    public function test_allocation_failure_rolls_back_new_service_and_resolution_work(): void
    {
        [
            ,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $before =
            ClientService::count();

        $caught =
            false;

        try {
            app(
                CompositeCommercialResolutionService::class
            )->resolve(
                compositeReviewId: (string) $review->id,

                targets: [
                    [
                        'service_name' => 'Should Roll Back',

                        'service_status' => 'historical',

                        /*
                         * Source is £1,500.
                         */
                        'allocated_net_pence' => 140000,
                    ],
                ],

                resolvedBy: $user->id
            );
        } catch (ValidationException) {
            $caught =
                true;
        }

        $this->assertTrue(
            $caught
        );

        $this->assertSame(
            $before,
            ClientService::count()
        );

        $this->assertSame(
            0,
            CommercialEvidenceAllocationSet::count()
        );

        $this->assertSame(
            0,
            CompositeCommercialResolutionTarget::count()
        );

        $this->assertCount(
            1,
            app(
                CompositeCommercialResolutionQueueService::class
            )->ready()
        );
    }

    private function reviewedEvidence(
        string $decision,
        string $date = '2026-08-31',
        float $netAmount = 1500,
        float $quantity = 3,
        string $description = 'Retainer, 3 days per month inc web dev , Sm & Marketing support (Reduced Day Rate BM approved)'
    ): array {
        $client =
            Client::factory()->create();

        return $this->reviewedEvidenceForClient(
            client: $client,
            decision: $decision,
            date: $date,
            netAmount: $netAmount,
            quantity: $quantity,
            description: $description
        );
    }

    private function reviewedEvidenceForClient(
        Client $client,
        string $decision,
        string $date,
        float $netAmount,
        float $quantity,
        string $description
    ): array {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'COMP-'
                    .str()->uuid(),

                'invoice_date' => $date,

                'status' => 'paid',
            ]);

        $item =
            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,

                'description' => $description,

                'quantity' => $quantity,

                'unit_price' => $netAmount
                    / $quantity,

                'net_amount' => $netAmount,
            ]);

        $fingerprint =
            app(
                CommercialServiceFingerprint::class
            )->fingerprint(
                (string) $item->description
            )['fingerprint'];

        $user =
            User::factory()->create();

        $reviews =
            app(
                CompositeCommercialReviewService::class
            );

        $review =
            match ($decision) {
                'bundled_service' => $reviews->bundledService(
                    clientId: (string) $client->id,

                    candidateFingerprint: $fingerprint,

                    invoiceItemId: (string) $item->id,

                    reviewedBy: $user->id,

                    asOf: CarbonImmutable::parse(
                        '2026-09-02'
                    )
                ),

                'requires_allocation' => $reviews->requiresAllocation(
                    clientId: (string) $client->id,

                    candidateFingerprint: $fingerprint,

                    invoiceItemId: (string) $item->id,

                    reviewedBy: $user->id,

                    asOf: CarbonImmutable::parse(
                        '2026-09-02'
                    )
                ),
            };

        return [
            $client,
            $item,
            $review,
            $user,
            $fingerprint,
        ];
    }

    private function service(
        Client $client,
        string $name,
        string $status
    ): ClientService {
        return ClientService::create([
            'client_id' => $client->id,

            'name' => $name,

            'type' => 'service',

            'status' => $status,
        ]);
    }

    private function directInvoiceItem(
        Client $client,
        ClientService $service,
        string $date,
        float $amount
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'DIRECT-'
                    .str()->uuid(),

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
