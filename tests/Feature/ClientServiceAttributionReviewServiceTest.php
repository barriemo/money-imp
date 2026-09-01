<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\ClientServiceAttributionCandidateService;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewService;
use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientServiceAttributionReview;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientServiceAttributionReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_attributes_exact_new_evidence_to_existing_canonical_service(): void
    {
        [
            $client,
            $service,
            $newItem,
            $user,
        ] = $this->confirmedServiceWithNewInvoice();

        $candidate =
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->firstOrFail();

        $review =
            app(
                ClientServiceAttributionReviewService::class
            )->approve(
                clientId: $client->id,
                candidateFingerprint: $candidate
                    ->candidateFingerprint,
                reviewedBy: $user->id,
                reason: 'Confirmed as the next hosting invoice.'
            );

        $this->assertSame(
            'approved',
            $review->decision
        );

        $this->assertSame(
            $service->id,
            $review->client_service_id
        );

        $this->assertSame(
            $user->id,
            $review->reviewed_by
        );

        $this->assertSame(
            [
                $newItem->id,
            ],
            $review
                ->candidate_snapshot[
                    'invoice_item_ids'
                ]
        );

        $this->assertSame(
            $service->id,
            $newItem
                ->fresh()
                ->client_service_id
        );

        /*
         * Approval extends attribution history.
         * It must not create another canonical service.
         */
        $this->assertSame(
            1,
            ClientService::count()
        );

        $this->assertSame(
            4,
            AccountingInvoiceItem::query()
                ->where(
                    'client_service_id',
                    $service->id
                )
                ->count()
        );

        $this->assertSame(
            0,
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->count()
        );
    }

    public function test_rejection_records_exact_review_without_attributing_invoice_evidence(): void
    {
        [
            $client,
            $service,
            $newItem,
            $user,
        ] = $this->confirmedServiceWithNewInvoice();

        $candidate =
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->firstOrFail();

        $review =
            app(
                ClientServiceAttributionReviewService::class
            )->reject(
                clientId: $client->id,
                candidateFingerprint: $candidate
                    ->candidateFingerprint,
                reviewedBy: $user->id,
                reason: 'This invoice line belongs elsewhere.'
            );

        $this->assertSame(
            'rejected',
            $review->decision
        );

        $this->assertSame(
            $service->id,
            $review->client_service_id
        );

        $this->assertNull(
            $newItem
                ->fresh()
                ->client_service_id
        );

        $this->assertSame(
            3,
            AccountingInvoiceItem::query()
                ->where(
                    'client_service_id',
                    $service->id
                )
                ->count()
        );

        $this->assertSame(
            0,
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->count()
        );
    }

    public function test_rejected_evidence_set_can_be_reassessed_when_new_evidence_changes_the_set(): void
    {
        [
            $client,
            $service,
            $newItem,
            $user,
        ] = $this->confirmedServiceWithNewInvoice();

        $queue =
            app(
                ClientServiceAttributionReviewQueueService::class
            );

        $candidate =
            $queue
                ->ready()
                ->firstOrFail();

        app(
            ClientServiceAttributionReviewService::class
        )->reject(
            clientId: $client->id,
            candidateFingerprint: $candidate
                ->candidateFingerprint,
            reviewedBy: $user->id
        );

        $this->assertSame(
            0,
            $queue
                ->ready()
                ->count()
        );

        $laterItem =
            $this->hostingObservation(
                $client,
                '2026-10-31',
                'HOST-2026-10-31'
            );

        $reopened =
            $queue
                ->ready()
                ->firstOrFail();

        $this->assertSame(
            'matched',
            $reopened->matchStatus
        );

        $this->assertSame(
            $service->id,
            $reopened->clientServiceId
        );

        $this->assertSame(
            2,
            $reopened->evidenceCount
        );

        $this->assertEqualsCanonicalizing(
            [
                $newItem->id,
                $laterItem->id,
            ],
            $reopened->invoiceItemIds
        );
    }

    public function test_inactive_target_cannot_be_approved(): void
    {
        [
            $client,
            $service,
            $newItem,
            $user,
        ] = $this->confirmedServiceWithNewInvoice();

        $service->update([
            'status' => 'inactive',
        ]);

        try {
            $candidate =
                app(
                    ClientServiceAttributionCandidateService::class
                )
                    ->forClient(
                        $client
                    )
                    ->firstOrFail();

            $this->assertSame(
                'inactive_target',
                $candidate->matchStatus
            );

            app(
                ClientServiceAttributionReviewService::class
            )->approve(
                clientId: $client->id,
                candidateFingerprint: $candidate
                    ->candidateFingerprint,
                reviewedBy: $user->id
            );

            $this->fail(
                'Expected inactive attribution target to fail.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'attribution',
                $exception->errors()
            );
        }

        $this->assertNull(
            $newItem
                ->fresh()
                ->client_service_id
        );

        $this->assertSame(
            0,
            ClientServiceAttributionReview::count()
        );
    }

    public function test_already_attributed_evidence_cannot_be_reviewed_again(): void
    {
        [
            $client,
            $service,
            $newItem,
            $user,
        ] = $this->confirmedServiceWithNewInvoice();

        $candidate =
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->firstOrFail();

        $newItem->update([
            'client_service_id' => $service->id,
        ]);

        try {
            app(
                ClientServiceAttributionReviewService::class
            )->approve(
                clientId: $client->id,
                candidateFingerprint: $candidate
                    ->candidateFingerprint,
                reviewedBy: $user->id
            );

            $this->fail(
                'Expected already-attributed evidence to fail.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'attribution',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            ClientServiceAttributionReview::count()
        );
    }

    private function confirmedServiceWithNewInvoice(): array
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
            $this->hostingObservation(
                $client,
                $date,
                'HOST-'.$date
            );
        }

        $user =
            User::factory()->create();

        $assessment =
            app(
                ClientServiceCandidateAssessmentService::class
            )
                ->all(
                    CarbonImmutable::parse(
                        '2026-09-01'
                    )
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

        $review =
            app(
                ClientServiceReconciliationService::class
            )->confirm(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                serviceName: 'Website Hosting',
                reviewedBy: $user->id,
                asOf: CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

        $service =
            ClientService::findOrFail(
                $review
                    ->client_service_id
            );

        $newItem =
            $this->hostingObservation(
                $client,
                '2026-09-30',
                'HOST-2026-09-30'
            );

        return [
            $client,
            $service,
            $newItem,
            $user,
        ];
    }

    private function hostingObservation(
        Client $client,
        string $date,
        string $invoiceNumber
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
            'description' => 'Monthly Hosting, Security Updates & Backups',
            'quantity' => 1,
            'unit_price' => 75,
            'net_amount' => 75,
        ]);
    }
}
