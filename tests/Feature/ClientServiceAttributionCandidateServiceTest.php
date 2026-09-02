<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\CommercialServiceFingerprint;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionCandidateService;
use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientServiceReconciliation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceAttributionCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_matching_invoice_after_confirmation_becomes_attribution_candidate_not_new_service_review(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $initialAsOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client,
                $initialAsOf
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
                asOf: $initialAsOf
            );

        $newItem =
            $this->hostingObservation(
                $client,
                '2026-09-30',
                'HOST-2026-09-30'
            );

        $laterAsOf =
            CarbonImmutable::parse(
                '2026-10-01'
            );

        /*
         * The existing service must NOT return to the
         * "does this service exist?" queue merely because
         * new matching invoice evidence appeared.
         */
        $this->assertSame(
            0,
            app(
                ClientServiceReconciliationQueueService::class
            )
                ->ready(
                    $laterAsOf
                )
                ->count()
        );

        $candidates =
            app(
                ClientServiceAttributionCandidateService::class
            )->forClient(
                $client
            );

        $this->assertCount(
            1,
            $candidates
        );

        $candidate =
            $candidates->first();

        $this->assertSame(
            'matched',
            $candidate->matchStatus
        );

        $this->assertTrue(
            $candidate->isReadyForReview()
        );

        $this->assertSame(
            $review->client_service_id,
            $candidate->clientServiceId
        );

        $this->assertSame(
            'Website Hosting',
            $candidate->clientServiceName
        );

        $this->assertSame(
            'hosting',
            $candidate->serviceType
        );

        $this->assertSame(
            1,
            $candidate->evidenceCount
        );

        $this->assertSame(
            [
                $newItem->id,
            ],
            $candidate->invoiceItemIds
        );

        /*
         * Historic confirmed observations stay attached to
         * canonical service truth.
         *
         * The new observation remains untouched until a human
         * approves attribution in a later write slice.
         */
        $this->assertSame(
            3,
            AccountingInvoiceItem::query()
                ->where(
                    'client_service_id',
                    $review->client_service_id
                )
                ->count()
        );

        $this->assertNull(
            $newItem
                ->fresh()
                ->client_service_id
        );
    }

    public function test_new_invoice_after_historical_confirmation_is_exposed_as_inactive_target_not_lost(): void
    {
        $client =
            Client::factory()->create();

        foreach (
            [
                '2026-04-30',
                '2026-05-29',
                '2026-06-30',
            ] as $date
        ) {
            $this->hostingObservation(
                $client,
                $date,
                'HISTORICAL-HOST-'.$date
            );
        }

        $user =
            User::factory()->create();

        $initialAsOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client,
                $initialAsOf
            );

        $this->assertSame(
            'recently_observed',
            $assessment->freshness
        );

        $review =
            app(
                ClientServiceReconciliationService::class
            )->confirmHistorical(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                serviceName: 'Website Hosting',
                reviewedBy: $user->id,
                asOf: $initialAsOf
            );

        $service =
            ClientService::findOrFail(
                $review->client_service_id
            );

        $this->assertSame(
            'historical',
            $service->status
        );

        $newItem =
            $this->hostingObservation(
                $client,
                '2026-09-30',
                'HOSTING-RETURNS'
            );

        $laterAsOf =
            CarbonImmutable::parse(
                '2026-10-01'
            );

        /*
         * It must not be mistaken for a brand-new service
         * existence review merely because new evidence appeared.
         */
        $this->assertSame(
            0,
            app(
                ClientServiceReconciliationQueueService::class
            )
                ->ready(
                    $laterAsOf
                )
                ->count()
        );

        $candidate =
            app(
                ClientServiceAttributionCandidateService::class
            )
                ->forClient(
                    $client
                )
                ->first();

        $this->assertNotNull(
            $candidate
        );

        /*
         * Crucially, the system remembers that this fingerprint
         * belongs to a known canonical service, but also remembers
         * that the service is NOT active.
         */
        $this->assertSame(
            'inactive_target',
            $candidate->matchStatus
        );

        $this->assertFalse(
            $candidate->isReadyForReview()
        );

        $this->assertNull(
            $candidate->clientServiceId
        );

        $this->assertSame(
            [
                $service->id,
            ],
            $candidate
                ->candidateClientServiceIds
        );

        $this->assertSame(
            [
                $newItem->id,
            ],
            $candidate->invoiceItemIds
        );

        /*
         * The new invoice is deliberately not attributed.
         *
         * Historical status must never silently become active.
         */
        $this->assertNull(
            $newItem
                ->fresh()
                ->client_service_id
        );

        $statusReviews =
            app(
                ClientServiceAttributionReviewQueueService::class
            )->inactiveTargets();

        $this->assertCount(
            1,
            $statusReviews
        );

        $this->assertSame(
            $client->id,
            $statusReviews
                ->first()
                ->clientId
        );

        $this->assertSame(
            'inactive_target',
            $statusReviews
                ->first()
                ->matchStatus
        );
    }

    public function test_unattributed_service_evidence_without_prior_human_mapping_is_unmatched(): void
    {
        $client =
            Client::factory()->create();

        $item =
            $this->hostingObservation(
                $client,
                '2026-09-30',
                'NEW-HOSTING'
            );

        $candidate =
            app(
                ClientServiceAttributionCandidateService::class
            )
                ->forClient(
                    $client
                )
                ->first();

        $this->assertNotNull(
            $candidate
        );

        $this->assertSame(
            'unmatched',
            $candidate->matchStatus
        );

        $this->assertFalse(
            $candidate->isReadyForReview()
        );

        $this->assertNull(
            $candidate->clientServiceId
        );

        $this->assertSame(
            [
                $item->id,
            ],
            $candidate->invoiceItemIds
        );
    }

    public function test_conflicting_prior_human_service_mappings_are_ambiguous_not_guessed(): void
    {
        $client =
            Client::factory()->create();

        $firstService =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Hosting One',
                'type' => 'service',
                'status' => 'active',
            ]);

        $secondService =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Hosting Two',
                'type' => 'service',
                'status' => 'active',
            ]);

        $fingerprint =
            app(
                CommercialServiceFingerprint::class
            )
                ->fingerprint(
                    'Monthly Hosting'
                )[
                    'fingerprint'
                ];

        foreach (
            [
                $firstService,
                $secondService,
            ] as $index => $service
        ) {
            ClientServiceReconciliation::create([
                'client_id' => $client->id,

                'candidate_fingerprint' => $fingerprint,

                'evidence_fingerprint' => hash(
                    'sha256',
                    'historic-'.$index
                ),

                'service_type' => 'hosting',

                'service_hint' => null,

                'decision' => 'merged',

                'client_service_id' => $service->id,

                'reviewed_by' => null,

                'reviewed_at' => now(),

                'candidate_snapshot' => [],
            ]);
        }

        $this->hostingObservation(
            $client,
            '2026-09-30',
            'AMBIGUOUS-HOSTING'
        );

        $candidate =
            app(
                ClientServiceAttributionCandidateService::class
            )
                ->forClient(
                    $client
                )
                ->first();

        $this->assertNotNull(
            $candidate
        );

        $this->assertSame(
            'ambiguous',
            $candidate->matchStatus
        );

        $this->assertFalse(
            $candidate->isReadyForReview()
        );

        $this->assertNull(
            $candidate->clientServiceId
        );

        $this->assertCount(
            2,
            $candidate
                ->candidateClientServiceIds
        );
    }

    public function test_mapping_to_inactive_service_is_not_presented_as_safe_match(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Old Hosting',
                'type' => 'service',
                'status' => 'inactive',
            ]);

        $fingerprint =
            app(
                CommercialServiceFingerprint::class
            )
                ->fingerprint(
                    'Monthly Hosting'
                )[
                    'fingerprint'
                ];

        ClientServiceReconciliation::create([
            'client_id' => $client->id,

            'candidate_fingerprint' => $fingerprint,

            'evidence_fingerprint' => hash(
                'sha256',
                'historic'
            ),

            'service_type' => 'hosting',

            'service_hint' => null,

            'decision' => 'merged',

            'client_service_id' => $service->id,

            'reviewed_by' => null,

            'reviewed_at' => now(),

            'candidate_snapshot' => [],
        ]);

        $this->hostingObservation(
            $client,
            '2026-09-30',
            'INACTIVE-HOSTING'
        );

        $candidate =
            app(
                ClientServiceAttributionCandidateService::class
            )
                ->forClient(
                    $client
                )
                ->first();

        $this->assertNotNull(
            $candidate
        );

        $this->assertSame(
            'inactive_target',
            $candidate->matchStatus
        );

        $this->assertFalse(
            $candidate->isReadyForReview()
        );

        $this->assertNull(
            $candidate->clientServiceId
        );
    }

    private function recurringHostingClient(): Client
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

        return $client;
    }

    private function readyAssessment(
        Client $client,
        CarbonImmutable $asOf
    ) {
        return app(
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
