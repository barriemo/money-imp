<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use App\Domains\CommercialTruth\Services\CanonicalServiceObservedBillingService;
use App\Domains\CommercialTruth\Services\CompositeCommercialAllocationService;
use App\Domains\CommercialTruth\Services\CompositeCommercialReviewService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialEvidenceAllocation;
use App\Models\CommercialEvidenceAllocationSet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompositeCommercialAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_is_one_exact_100_percent_allocation_without_direct_attribution(): void
    {
        [
            $client,
            $item,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $service =
            $this->service(
                $client,
                'Bundled Digital Retainer',
                'active'
            );

        $set =
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

                allocatedBy: $user->id,

                reason: 'One commercial bundle.'
            );

        $this->assertSame(
            'bundle',
            $set->allocation_kind
        );

        $this->assertSame(
            150000,
            $set->source_net_pence
        );

        $this->assertCount(
            1,
            $set->allocations
        );

        $this->assertSame(
            150000,
            $set->allocations
                ->first()
                ->allocated_net_pence
        );

        /*
         * Source evidence remains immutable and is not converted
         * into the legacy one-service attribution shape.
         */
        $this->assertNull(
            $item->fresh()
                ->client_service_id
        );

        /*
         * Stage 2B now allows this approved allocation to become
         * canonical observed billing evidence without converting
         * the source item into legacy direct attribution.
         *
         * One old observation is still only one-off historical
         * evidence, so it does not establish current recurring
         * monthly value.
         */
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
            'one_off',
            $truth->cadence
        );

        $this->assertSame(
            1500.0,
            $truth->signedObservedNet
        );

        $this->assertNull(
            $truth->currentMonthlyEquivalent
        );
    }

    public function test_split_conserves_source_value_exactly_across_multiple_services(): void
    {
        [
            $client,
            $item,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'requires_allocation'
        );

        $development =
            $this->service(
                $client,
                'Web Development',
                'active'
            );

        $marketing =
            $this->service(
                $client,
                'Marketing Support',
                'historical'
            );

        $set =
            app(
                CompositeCommercialAllocationService::class
            )->allocate(
                compositeReviewId: (string) $review->id,

                allocations: [
                    [
                        'client_service_id' => (string) $development->id,

                        'allocated_net_pence' => 100000,
                    ],
                    [
                        'client_service_id' => (string) $marketing->id,

                        'allocated_net_pence' => 50000,
                    ],
                ],

                allocatedBy: $user->id
            );

        $this->assertSame(
            'split',
            $set->allocation_kind
        );

        $this->assertSame(
            150000,
            $set->source_net_pence
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
            CommercialEvidenceAllocation::count()
        );

        $this->assertNull(
            $item->fresh()
                ->client_service_id
        );
    }

    public function test_non_conserving_split_is_rejected(): void
    {
        [
            $client,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'requires_allocation'
        );

        $first =
            $this->service(
                $client,
                'First Service'
            );

        $second =
            $this->service(
                $client,
                'Second Service'
            );

        $this->expectException(
            ValidationException::class
        );

        app(
            CompositeCommercialAllocationService::class
        )->allocate(
            compositeReviewId: (string) $review->id,

            allocations: [
                [
                    'client_service_id' => (string) $first->id,

                    'allocated_net_pence' => 100000,
                ],
                [
                    'client_service_id' => (string) $second->id,

                    'allocated_net_pence' => 40000,
                ],
            ],

            allocatedBy: $user->id
        );
    }

    public function test_cross_client_allocation_target_is_rejected(): void
    {
        [
            $client,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'requires_allocation'
        );

        $sameClient =
            $this->service(
                $client,
                'Same Client Service'
            );

        $otherClient =
            Client::factory()->create();

        $foreign =
            $this->service(
                $otherClient,
                'Foreign Client Service'
            );

        $this->expectException(
            ValidationException::class
        );

        app(
            CompositeCommercialAllocationService::class
        )->allocate(
            compositeReviewId: (string) $review->id,

            allocations: [
                [
                    'client_service_id' => (string) $sameClient->id,

                    'allocated_net_pence' => 100000,
                ],
                [
                    'client_service_id' => (string) $foreign->id,

                    'allocated_net_pence' => 50000,
                ],
            ],

            allocatedBy: $user->id
        );
    }

    public function test_changed_source_evidence_invalidates_structural_review_before_allocation(): void
    {
        [
            $client,
            $item,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $service =
            $this->service(
                $client,
                'Bundled Retainer'
            );

        $item->update([
            'net_amount' => 1600,
        ]);

        $this->expectException(
            ValidationException::class
        );

        app(
            CompositeCommercialAllocationService::class
        )->allocate(
            compositeReviewId: (string) $review->id,

            allocations: [
                [
                    'client_service_id' => (string) $service->id,

                    'allocated_net_pence' => 160000,
                ],
            ],

            allocatedBy: $user->id
        );
    }

    public function test_exact_reviewed_evidence_cannot_be_allocated_twice(): void
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
                'Bundled Retainer'
            );

        $allocator =
            app(
                CompositeCommercialAllocationService::class
            );

        $allocation = [
            [
                'client_service_id' => (string) $service->id,

                'allocated_net_pence' => 150000,
            ],
        ];

        $allocator->allocate(
            compositeReviewId: (string) $review->id,

            allocations: $allocation,

            allocatedBy: $user->id
        );

        $this->expectException(
            ValidationException::class
        );

        $allocator->allocate(
            compositeReviewId: (string) $review->id,

            allocations: $allocation,

            allocatedBy: $user->id
        );
    }

    public function test_requires_allocation_review_cannot_collapse_to_one_target(): void
    {
        [
            $client,
            ,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'requires_allocation'
        );

        $service =
            $this->service(
                $client,
                'One Service'
            );

        $this->expectException(
            ValidationException::class
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
    }

    public function test_allocated_review_cannot_be_deleted_and_take_allocation_audit_history_with_it(): void
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
                'Bundled Retainer'
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

        $this->assertSame(
            1,
            CommercialEvidenceAllocationSet::count()
        );

        $this->expectException(
            QueryException::class
        );

        $review->delete();
    }

    public function test_database_rejects_unknown_allocation_kind(): void
    {
        [
            $client,
            $item,
            $review,
            $user,
        ] = $this->reviewedEvidence(
            'bundled_service'
        );

        $this->expectException(
            QueryException::class
        );

        CommercialEvidenceAllocationSet::create([
            'composite_commercial_review_id' => $review->id,

            'accounting_invoice_item_id' => $item->id,

            'client_id' => $client->id,

            'evidence_fingerprint' => $review->evidence_fingerprint,

            'allocation_kind' => 'invented',

            'source_net_pence' => 150000,

            'allocated_by' => $user->id,

            'allocated_at' => now(),

            'allocation_snapshot' => [],
        ]);
    }

    public function test_database_prevents_second_allocation_set_for_same_reviewed_evidence(): void
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
                'Bundled Retainer'
            );

        $first =
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

        $this->expectException(
            QueryException::class
        );

        CommercialEvidenceAllocationSet::create([
            'composite_commercial_review_id' => $review->id,

            'accounting_invoice_item_id' => $first
                ->accounting_invoice_item_id,

            'client_id' => $first->client_id,

            'evidence_fingerprint' => $first
                ->evidence_fingerprint,

            'allocation_kind' => 'bundle',

            'source_net_pence' => 150000,

            'allocated_by' => $user->id,

            'allocated_at' => now(),

            'allocation_snapshot' => [],
        ]);
    }

    public function test_database_prevents_duplicate_service_line_within_allocation_set(): void
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
                'Bundled Retainer'
            );

        $set =
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

        $this->expectException(
            QueryException::class
        );

        CommercialEvidenceAllocation::create([
            'allocation_set_id' => $set->id,

            'client_service_id' => $service->id,

            'allocated_net_pence' => 150000,
        ]);
    }

    public function test_allocation_set_cannot_be_deleted_while_allocation_lines_exist(): void
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
                'Bundled Retainer'
            );

        $set =
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

        $this->expectException(
            QueryException::class
        );

        $set->delete();
    }

    public function test_allocated_canonical_service_cannot_be_force_deleted_and_erase_allocation_history(): void
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
                'Bundled Retainer'
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

        $this->expectException(
            QueryException::class
        );

        $service->forceDelete();
    }

    private function reviewedEvidence(
        string $decision
    ): array {
        $client =
            Client::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'COMPOSITE-ALLOC',

                'invoice_date' => '2025-01-27',

                'status' => 'paid',
            ]);

        $item =
            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,

                'description' => 'Retainer, 3 days per month inc web dev , Sm & Marketing support (Reduced Day Rate BM approved)',

                'quantity' => 3,

                'unit_price' => 500,

                'net_amount' => 1500,
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

                default => throw new \LogicException(
                    'Unsupported test decision.'
                ),
            };

        return [
            $client,
            $item,
            $review,
            $user,
        ];
    }

    private function service(
        Client $client,
        string $name,
        string $status = 'active'
    ): ClientService {
        return ClientService::create([
            'client_id' => $client->id,

            'name' => $name,

            'status' => $status,
        ]);
    }
}
