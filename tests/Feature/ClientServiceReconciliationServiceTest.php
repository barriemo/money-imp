<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationService;
use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientServiceReconciliation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientServiceReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejection_records_exact_human_review_without_creating_canonical_truth(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $assessment =
            $this->readyAssessment(
                $client
            );

        $review =
            app(
                ClientServiceReconciliationService::class
            )->reject(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                reviewedBy: $user->id,
                reason: 'This is not an active client service.',
                asOf: CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

        $this->assertSame(
            'rejected',
            $review->decision
        );

        $this->assertSame(
            $client->id,
            $review->client_id
        );

        $this->assertSame(
            $user->id,
            $review->reviewed_by
        );

        $this->assertSame(
            $assessment
                ->candidate
                ->fingerprint,
            $review
                ->candidate_fingerprint
        );

        $this->assertSame(
            64,
            strlen(
                $review
                    ->evidence_fingerprint
            )
        );

        $this->assertSame(
            $assessment
                ->candidate
                ->invoiceItemIds,
            $review
                ->candidate_snapshot[
                    'invoice_item_ids'
                ]
        );

        $this->assertSame(
            'ready_for_review',
            $review
                ->candidate_snapshot[
                    'promotion_readiness'
                ]
        );

        $this->assertSame(
            0,
            ClientService::count()
        );

        $this->assertSame(
            0,
            AccountingInvoiceItem::query()
                ->whereNotNull(
                    'client_service_id'
                )
                ->count()
        );
    }

    public function test_rejected_exact_evidence_is_removed_from_review_queue(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client
            );

        $queue =
            app(
                ClientServiceReconciliationQueueService::class
            );

        $this->assertSame(
            1,
            $queue
                ->ready($asOf)
                ->count()
        );

        app(
            ClientServiceReconciliationService::class
        )->reject(
            clientId: $client->id,
            candidateFingerprint: $assessment
                ->candidate
                ->fingerprint,
            reviewedBy: $user->id,
            asOf: $asOf
        );

        $this->assertSame(
            0,
            $queue
                ->ready($asOf)
                ->count()
        );
    }

    public function test_deferred_candidate_remains_ready_for_future_human_review(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client
            );

        $review =
            app(
                ClientServiceReconciliationService::class
            )->defer(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                reviewedBy: $user->id,
                reason: 'Check the current agreement before deciding.',
                asOf: $asOf
            );

        $this->assertSame(
            'deferred',
            $review->decision
        );

        $this->assertSame(
            1,
            app(
                ClientServiceReconciliationQueueService::class
            )
                ->ready($asOf)
                ->count()
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_candidate_not_ready_for_review_cannot_receive_human_reconciliation_decision(): void
    {
        $client =
            Client::factory()->create();

        $invoice =
            $this->invoice(
                $client,
                '2026-08-31'
            );

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'description' => 'Monthly Hosting, Security Updates & Backups',
            'quantity' => 1,
            'unit_price' => 75,
            'net_amount' => 75,
        ]);

        $assessment =
            app(
                ClientServiceCandidateAssessmentService::class
            )
                ->all(
                    CarbonImmutable::parse(
                        '2026-09-01'
                    )
                )
                ->first(
                    fn ($row) => $row->candidate
                        ->clientId
                            === $client->id
                );

        $this->assertNotNull(
            $assessment
        );

        $this->assertNotSame(
            'ready_for_review',
            $assessment
                ->promotionReadiness
        );

        $user =
            User::factory()->create();

        try {
            app(
                ClientServiceReconciliationService::class
            )->reject(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                reviewedBy: $user->id,
                asOf: CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

            $this->fail(
                'Expected candidate reconciliation to be rejected.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'candidate',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            ClientServiceReconciliation::count()
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_rejection_changes_review_count_without_changing_supported_commercial_value(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client
            );

        $before =
            app(
                CurrentCommercialPositionService::class
            )->position(
                $asOf
            );

        $this->assertSame(
            1,
            $before
                ->readyForReviewCount
        );

        $this->assertSame(
            75.0,
            $before
                ->supportedCurrentMonthlyEquivalent
        );

        app(
            ClientServiceReconciliationService::class
        )->reject(
            clientId: $client->id,
            candidateFingerprint: $assessment
                ->candidate
                ->fingerprint,
            reviewedBy: $user->id,
            asOf: $asOf
        );

        $after =
            app(
                CurrentCommercialPositionService::class
            )->position(
                $asOf
            );

        $this->assertSame(
            0,
            $after
                ->readyForReviewCount
        );

        /*
         * Rejection resolves the human review queue.
         *
         * It does not rewrite the underlying invoice-history
         * evidence or pretend that the observed billing vanished.
         */
        $this->assertSame(
            75.0,
            $after
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_confirm_creates_canonical_service_and_attributes_exact_reviewed_evidence(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client
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
                reason: 'Confirmed against current client service.',
                asOf: $asOf
            );

        $this->assertSame(
            'confirmed',
            $review->decision
        );

        $this->assertNotNull(
            $review->client_service_id
        );

        $service =
            ClientService::findOrFail(
                $review->client_service_id
            );

        $this->assertSame(
            $client->id,
            $service->client_id
        );

        $this->assertSame(
            'Website Hosting',
            $service->name
        );

        $this->assertSame(
            'service',
            $service->type
        );

        $this->assertSame(
            'active',
            $service->status
        );

        $this->assertSame(
            'hosting',
            $service->metadata[
                'classified_service_type'
            ]
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
                ClientServiceReconciliationQueueService::class
            )
                ->ready($asOf)
                ->count()
        );
    }

    public function test_merge_attributes_reviewed_evidence_to_existing_service_without_creating_another_service(): void
    {
        $client =
            $this->recurringHostingClient();

        $existing =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Managed Website Service',
                'type' => 'service',
                'status' => 'active',
            ]);

        $user =
            User::factory()->create();

        $asOf =
            CarbonImmutable::parse(
                '2026-09-01'
            );

        $assessment =
            $this->readyAssessment(
                $client
            );

        $review =
            app(
                ClientServiceReconciliationService::class
            )->merge(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                clientServiceId: $existing->id,
                reviewedBy: $user->id,
                asOf: $asOf
            );

        $this->assertSame(
            'merged',
            $review->decision
        );

        $this->assertSame(
            $existing->id,
            $review->client_service_id
        );

        $this->assertSame(
            1,
            ClientService::count()
        );

        $this->assertSame(
            3,
            AccountingInvoiceItem::query()
                ->where(
                    'client_service_id',
                    $existing->id
                )
                ->count()
        );

        $this->assertSame(
            0,
            app(
                ClientServiceReconciliationQueueService::class
            )
                ->ready($asOf)
                ->count()
        );
    }

    public function test_merge_refuses_client_service_owned_by_another_client(): void
    {
        $client =
            $this->recurringHostingClient();

        $otherClient =
            Client::factory()->create();

        $existing =
            ClientService::create([
                'client_id' => $otherClient->id,
                'name' => 'Other Client Service',
                'type' => 'service',
                'status' => 'active',
            ]);

        $user =
            User::factory()->create();

        $assessment =
            $this->readyAssessment(
                $client
            );

        try {
            app(
                ClientServiceReconciliationService::class
            )->merge(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                clientServiceId: $existing->id,
                reviewedBy: $user->id,
                asOf: CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

            $this->fail(
                'Expected cross-client service merge to fail.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'client_service',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            ClientServiceReconciliation::count()
        );

        $this->assertSame(
            0,
            AccountingInvoiceItem::query()
                ->whereNotNull(
                    'client_service_id'
                )
                ->count()
        );
    }

    public function test_confirm_requires_explicit_human_canonical_service_name(): void
    {
        $client =
            $this->recurringHostingClient();

        $user =
            User::factory()->create();

        $assessment =
            $this->readyAssessment(
                $client
            );

        try {
            app(
                ClientServiceReconciliationService::class
            )->confirm(
                clientId: $client->id,
                candidateFingerprint: $assessment
                    ->candidate
                    ->fingerprint,
                serviceName: '   ',
                reviewedBy: $user->id,
                asOf: CarbonImmutable::parse(
                    '2026-09-01'
                )
            );

            $this->fail(
                'Expected blank canonical service name to fail.'
            );
        } catch (
            ValidationException $exception
        ) {
            $this->assertArrayHasKey(
                'service_name',
                $exception->errors()
            );
        }

        $this->assertSame(
            0,
            ClientService::count()
        );

        $this->assertSame(
            0,
            ClientServiceReconciliation::count()
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
            $invoice =
                $this->invoice(
                    $client,
                    $date
                );

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Monthly Hosting, Security Updates & Backups',
                'quantity' => 1,
                'unit_price' => 75,
                'net_amount' => 75,
            ]);
        }

        return $client;
    }

    private function readyAssessment(
        Client $client
    ) {
        return app(
            ClientServiceCandidateAssessmentService::class
        )
            ->all(
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->firstOrFail(
                fn ($row) => $row->candidate
                    ->clientId
                        === $client->id
                    && $row
                        ->promotionReadiness
                        === 'ready_for_review'
            );
    }

    private function invoice(
        Client $client,
        string $date
    ): AccountingInvoice {
        return AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => (string) str()->uuid(),
            'invoice_date' => $date,
            'status' => 'paid',
        ]);
    }
}
