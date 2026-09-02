<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use App\Domains\CommercialTruth\Services\CompositeCommercialEvidenceFingerprint;
use App\Domains\CommercialTruth\Services\CompositeCommercialEvidenceReviewQueueService;
use App\Domains\CommercialTruth\Services\CompositeCommercialReviewService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CompositeCommercialReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompositeCommercialReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundled_service_decision_records_structural_review_without_creating_canonical_truth(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        $beforeServices =
            ClientService::count();

        $review =
            app(
                CompositeCommercialReviewService::class
            )->bundledService(
                clientId: (string) $client->id,
                candidateFingerprint: $fingerprint,
                invoiceItemId: (string) $item->id,
                reviewedBy: $user->id,
                reason: 'One bundled retainer package.',
                asOf: CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertSame(
            'bundled_service',
            $review->decision
        );

        $this->assertSame(
            (string) $item->id,
            (string) $review
                ->accounting_invoice_item_id
        );

        $this->assertSame(
            [
                'retainer',
                'support',
                'development',
            ],
            $review
                ->candidate_snapshot[
                    'detected_activity_families'
                ]
        );

        $this->assertSame(
            $beforeServices,
            ClientService::count()
        );

        $this->assertNull(
            $item->fresh()
                ->client_service_id
        );
    }

    public function test_requires_allocation_records_structure_without_inventing_allocations(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        $review =
            app(
                CompositeCommercialReviewService::class
            )->requiresAllocation(
                clientId: (string) $client->id,
                candidateFingerprint: $fingerprint,
                invoiceItemId: (string) $item->id,
                reviewedBy: $user->id,
                reason: 'Source value requires a human monetary split.',
                asOf: CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertSame(
            'requires_allocation',
            $review->decision
        );

        $this->assertSame(
            1,
            CompositeCommercialReview::count()
        );

        $this->assertNull(
            $item->fresh()
                ->client_service_id
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_deferred_review_remains_in_composite_review_queue(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        $service =
            app(
                CompositeCommercialReviewService::class
            );

        $service->defer(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
        );

        $queue =
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertCount(
            1,
            $queue
        );
    }

    public function test_terminal_structural_review_removes_exact_item_from_review_queue(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        app(
            CompositeCommercialReviewService::class
        )->bundledService(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
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
    }

    public function test_terminal_structural_review_cannot_be_silently_overwritten(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        $service =
            app(
                CompositeCommercialReviewService::class
            );

        $service->bundledService(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
        );

        $this->expectException(
            ValidationException::class
        );

        $service->requiresAllocation(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
        );
    }

    public function test_same_family_fingerprint_still_requires_exact_invoice_item_identity(): void
    {
        $client =
            Client::factory()->create();

        $first =
            $this->invoiceItem(
                client: $client,
                invoiceNumber: 'FIB-1',
                date: '2024-12-30'
            );

        $second =
            $this->invoiceItem(
                client: $client,
                invoiceNumber: 'FIB-2',
                date: '2025-01-27'
            );

        $fingerprint =
            app(
                CommercialServiceFingerprint::class
            )->fingerprint(
                (string) $first->description
            )['fingerprint'];

        $user =
            User::factory()->create();

        app(
            CompositeCommercialReviewService::class
        )->bundledService(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $first->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
        );

        $queue =
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertCount(
            1,
            $queue
        );

        $this->assertSame(
            [
                (string) $second->id,
            ],
            $queue
                ->first()
                ->candidate
                ->invoiceItemIds
        );
    }

    public function test_evidence_fingerprint_changes_when_atomic_source_state_changes(): void
    {
        [, $item] =
            $this->compositeEvidence();

        $fingerprints =
            app(
                CompositeCommercialEvidenceFingerprint::class
            );

        $item->load('invoice');

        $before =
            $fingerprints
                ->forInvoiceItem(
                    $item
                );

        $item->update([
            'net_amount' => 1600,
        ]);

        $item->refresh();
        $item->load('invoice');

        $after =
            $fingerprints
                ->forInvoiceItem(
                    $item
                );

        $this->assertNotSame(
            $before,
            $after
        );
    }

    public function test_changed_source_state_reappears_after_prior_terminal_review(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        app(
            CompositeCommercialReviewService::class
        )->bundledService(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
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

        /*
         * The activity classification remains composite and the
         * family fingerprint remains stable, but the monetary source
         * evidence itself has changed.
         */
        $item->update([
            'net_amount' => 1600,
        ]);

        $queue =
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertCount(
            1,
            $queue
        );

        $this->assertSame(
            [
                (string) $item->id,
            ],
            $queue
                ->first()
                ->candidate
                ->invoiceItemIds
        );
    }

    public function test_deferred_history_can_be_superseded_by_terminal_structural_review(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        $service =
            app(
                CompositeCommercialReviewService::class
            );

        $service->defer(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
        );

        $service->bundledService(
            clientId: (string) $client->id,
            candidateFingerprint: $fingerprint,
            invoiceItemId: (string) $item->id,
            reviewedBy: $user->id,
            asOf: CarbonImmutable::parse(
                '2026-09-02'
            )
        );

        $this->assertSame(
            2,
            CompositeCommercialReview::count()
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
    }

    public function test_database_prevents_conflicting_terminal_decisions_for_same_exact_evidence_state(): void
    {
        [$client, $item, $fingerprint] =
            $this->compositeEvidence();

        $user =
            User::factory()->create();

        $first =
            app(
                CompositeCommercialReviewService::class
            )->bundledService(
                clientId: (string) $client->id,
                candidateFingerprint: $fingerprint,
                invoiceItemId: (string) $item->id,
                reviewedBy: $user->id,
                asOf: CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->expectException(
            QueryException::class
        );

        CompositeCommercialReview::create([
            'accounting_invoice_item_id' => $first->accounting_invoice_item_id,

            'client_id' => $first->client_id,

            'candidate_fingerprint' => $first->candidate_fingerprint,

            'evidence_fingerprint' => $first->evidence_fingerprint,

            'terminal_marker' => 'terminal',

            'decision' => 'requires_allocation',

            'reviewed_by' => $user->id,

            'reviewed_at' => now(),

            'candidate_snapshot' => $first->candidate_snapshot,
        ]);
    }

    private function compositeEvidence(): array
    {
        $client =
            Client::factory()->create();

        $item =
            $this->invoiceItem(
                client: $client,
                invoiceNumber: 'FIB-TEST',
                date: '2025-01-27'
            );

        $fingerprint =
            app(
                CommercialServiceFingerprint::class
            )->fingerprint(
                (string) $item->description
            )['fingerprint'];

        return [
            $client,
            $item,
            $fingerprint,
        ];
    }

    private function invoiceItem(
        Client $client,
        string $invoiceNumber,
        string $date
    ): AccountingInvoiceItem {
        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => $invoiceNumber,

                'invoice_date' => $date,

                'status' => 'paid',
            ]);

        return AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,

            'description' => 'Retainer, 3 days per month inc web dev , Sm & Marketing support (Reduced Day Rate BM approved)',

            'quantity' => 3,

            'unit_price' => 500,

            'net_amount' => 1500,
        ]);
    }
}
