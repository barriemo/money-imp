<?php

namespace Tests\Feature\CommercialReconciliation;

use App\Domains\CommercialTruth\Services\ClientServiceAttributionReviewQueueService;
use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Domains\CommercialTruth\Services\ClientServiceReconciliationService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\ClientServiceAttributionReview;
use App\Models\ClientServiceReconciliation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialReconciliationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_review_inbox_shows_candidate_and_exact_invoice_evidence(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Walker The Jeweller Ltd',
            ]);

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
                description: 'Social Media Retainer',
                amount: 600
            );
        }

        $this->actingAs($user)
            ->get(
                route(
                    'reconciliation.commercial.index',
                    [
                        'queue' => 'services',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Walker The Jeweller Ltd'
            )
            ->assertSee(
                'social media',
                false
            )
            ->assertSee(
                '£600.00'
            )
            ->assertSee(
                'Social Media Retainer'
            )
            ->assertSee(
                'Confirm New Service'
            )
            ->assertSee(
                'Reject Evidence'
            )
            ->assertSee(
                'This is not contracted MRR.'
            );
    }

    public function test_composite_evidence_has_separate_read_only_decomposition_inbox(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'MML Law',
            ]);

        $this->invoiceItem(
            client: $client,
            date: '2026-07-31',
            description: 'Monthly Consultancy / Implementations / Support (retainer) / Website Development / App Development / SEO / Content .',
            amount: 4000
        );

        $response =
            $this->actingAs($user)
                ->get(
                    route(
                        'reconciliation.commercial.index',
                        [
                            'queue' => 'composite',
                        ]
                    )
                );

        $response
            ->assertOk()
            ->assertSee(
                'Composite evidence 1'
            )
            ->assertSee(
                'MML Law'
            )
            ->assertSee(
                'Composite commercial evidence'
            )
            ->assertSee(
                'Needs decomposition'
            )
            ->assertSee(
                '£4,000.00'
            )
            ->assertSee(
                'retainer'
            )
            ->assertSee(
                'support'
            )
            ->assertSee(
                'development'
            )
            ->assertSee(
                'seo'
            )
            ->assertSee(
                'content'
            )
            ->assertSee(
                'Review exact composite invoice evidence'
            )
            ->assertSee(
                'cannot truthfully be assigned'
            )
            ->assertSee(
                'no current recurring commercial value'
            )
            ->assertSee(
                'Read-only review surface'
            )
            ->assertDontSee(
                'Confirm New Service'
            )
            ->assertDontSee(
                'Confirm Historical Service'
            )
            ->assertDontSee(
                'Merge Into Existing'
            )
            ->assertDontSee(
                'Approve Attribution'
            )
            ->assertDontSee(
                'Reject Evidence'
            );

        $this->assertSame(
            0,
            ClientService::count()
        );

        $this->assertSame(
            0,
            ClientServiceReconciliation::count()
        );
    }

    public function test_confirming_service_creates_canonical_service_and_attributes_exact_history(): void
    {
        $user =
            User::factory()->create();

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
                amount: 75
            );
        }

        $candidate =
            $this->readyAssessment(
                $client
            );

        $this->actingAs($user)
            ->post(
                route(
                    'reconciliation.commercial.confirm',
                    [
                        'clientId' => $client->id,
                        'candidateFingerprint' => $candidate
                            ->candidate
                            ->fingerprint,
                    ]
                ),
                [
                    'service_name' => 'Website Hosting',
                    'reason' => 'Confirmed from long-running monthly billing.',
                ]
            )
            ->assertRedirect(
                route(
                    'reconciliation.commercial.index',
                    [
                        'queue' => 'services',
                    ]
                )
            );

        $service =
            ClientService::firstOrFail();

        $this->assertSame(
            'Website Hosting',
            $service->name
        );

        $this->assertSame(
            'active',
            $service->status
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

        $review =
            ClientServiceReconciliation::firstOrFail();

        $this->assertSame(
            'confirmed',
            $review->decision
        );

        $this->assertSame(
            $user->id,
            $review->reviewed_by
        );
    }

    public function test_recently_observed_candidate_can_be_confirmed_as_historical_from_inbox(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Historical Hosting Client',
            ]);

        foreach (
            [
                '2026-04-30',
                '2026-05-29',
                '2026-06-30',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75
            );
        }

        $candidate =
            $this->readyAssessment(
                $client
            );

        $this->assertSame(
            'recently_observed',
            $candidate->freshness
        );

        $this->actingAs($user)
            ->get(
                route(
                    'reconciliation.commercial.index',
                    [
                        'queue' => 'services',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Historical Hosting Client'
            )
            ->assertSee(
                'Confirm Historical Service'
            )
            ->assertDontSee(
                'Confirm New Service'
            )
            ->assertDontSee(
                'Merge Into Existing'
            )
            ->assertSee(
                'current active status is not established'
            );

        $this->actingAs($user)
            ->post(
                route(
                    'reconciliation.commercial.historical',
                    [
                        'clientId' => $client->id,

                        'candidateFingerprint' => $candidate
                            ->candidate
                            ->fingerprint,
                    ]
                ),
                [
                    'service_name' => 'Website Hosting',

                    'reason' => 'Historical recurring service confirmed from invoice evidence; current active status not established.',
                ]
            )
            ->assertRedirect(
                route(
                    'reconciliation.commercial.index',
                    [
                        'queue' => 'services',
                    ]
                )
            );

        $service =
            ClientService::firstOrFail();

        $this->assertSame(
            'Website Hosting',
            $service->name
        );

        $this->assertSame(
            'historical',
            $service->status
        );

        $this->assertNull(
            $service->ends_on
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

        $review =
            ClientServiceReconciliation::firstOrFail();

        $this->assertSame(
            'confirmed_historical',
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
    }

    public function test_new_evidence_for_historical_service_is_visible_in_status_review_inbox(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Returning Historical Client',
            ]);

        foreach (
            [
                '2026-04-30',
                '2026-05-29',
                '2026-06-30',
            ] as $date
        ) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75
            );
        }

        $candidate =
            $this->readyAssessment(
                $client
            );

        $review =
            app(
                ClientServiceReconciliationService::class
            )->confirmHistorical(
                clientId: $client->id,
                candidateFingerprint: $candidate
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
                $review->client_service_id
            );

        $this->assertSame(
            'historical',
            $service->status
        );

        $newItem =
            $this->invoiceItem(
                client: $client,
                date: '2026-09-30',
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75
            );

        $this->assertNull(
            $newItem->client_service_id
        );

        $this->actingAs($user)
            ->get(
                route(
                    'reconciliation.commercial.index',
                    [
                        'queue' => 'status',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Service status 1'
            )
            ->assertSee(
                'Returning Historical Client'
            )
            ->assertSee(
                'Non-active service status review'
            )
            ->assertSee(
                'Inactive canonical target'
            )
            ->assertSee(
                'new unattributed evidence'
            )
            ->assertSee(
                'will not reactivate'
            )
            ->assertSee(
                'Monthly Hosting, Security Updates & Backups'
            )
            ->assertDontSee(
                'Approve Attribution'
            );

        /*
         * Merely opening the review inbox must never mutate
         * either the evidence or canonical service status.
         */
        $this->assertNull(
            $newItem
                ->fresh()
                ->client_service_id
        );

        $this->assertSame(
            'historical',
            $service
                ->fresh()
                ->status
        );
    }

    public function test_candidate_can_be_merged_into_existing_active_service(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Website Hosting',
                'type' => 'service',
                'status' => 'active',
            ]);

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
                amount: 75
            );
        }

        $candidate =
            $this->readyAssessment(
                $client
            );

        $this->actingAs($user)
            ->post(
                route(
                    'reconciliation.commercial.merge',
                    [
                        'clientId' => $client->id,
                        'candidateFingerprint' => $candidate
                            ->candidate
                            ->fingerprint,
                    ]
                ),
                [
                    'client_service_id' => $service->id,
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            1,
            ClientService::count()
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
            'merged',
            ClientServiceReconciliation::firstOrFail()
                ->decision
        );
    }

    public function test_inactive_service_cannot_receive_merged_evidence(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Old Hosting Service',
                'type' => 'service',
                'status' => 'inactive',
            ]);

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
                amount: 75
            );
        }

        $candidate =
            $this->readyAssessment(
                $client
            );

        $this->actingAs($user)
            ->from(
                route(
                    'reconciliation.commercial.index'
                )
            )
            ->post(
                route(
                    'reconciliation.commercial.merge',
                    [
                        'clientId' => $client->id,
                        'candidateFingerprint' => $candidate
                            ->candidate
                            ->fingerprint,
                    ]
                ),
                [
                    'client_service_id' => $service->id,
                ]
            )
            ->assertRedirect(
                route(
                    'reconciliation.commercial.index'
                )
            )
            ->assertSessionHasErrors(
                'client_service'
            );

        $this->assertSame(
            0,
            AccountingInvoiceItem::query()
                ->whereNotNull(
                    'client_service_id'
                )
                ->count()
        );

        $this->assertSame(
            0,
            ClientServiceReconciliation::count()
        );
    }

    public function test_rejecting_service_evidence_records_decision_without_creating_canonical_truth(): void
    {
        $user =
            User::factory()->create();

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
                amount: 75
            );
        }

        $candidate =
            $this->readyAssessment(
                $client
            );

        $this->actingAs($user)
            ->post(
                route(
                    'reconciliation.commercial.reject',
                    [
                        'clientId' => $client->id,
                        'candidateFingerprint' => $candidate
                            ->candidate
                            ->fingerprint,
                    ]
                ),
                [
                    'reason' => 'This billing history is not a live client service.',
                ]
            )
            ->assertRedirect();

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

        $review =
            ClientServiceReconciliation::firstOrFail();

        $this->assertSame(
            'rejected',
            $review->decision
        );
    }

    public function test_new_invoice_for_confirmed_service_appears_in_attribution_queue_and_can_be_approved(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'City Blinds',
            ]);

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
                amount: 75
            );
        }

        $assessment =
            $this->readyAssessment(
                $client
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
            asOf: CarbonImmutable::parse(
                '2026-09-01'
            ),
        );

        $newItem =
            $this->invoiceItem(
                client: $client,
                date: '2026-09-30',
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75
            );

        $attribution =
            app(
                ClientServiceAttributionReviewQueueService::class
            )
                ->ready()
                ->firstOrFail();

        $this->actingAs($user)
            ->get(
                route(
                    'reconciliation.commercial.index',
                    [
                        'queue' => 'attribution',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'City Blinds'
            )
            ->assertSee(
                'Website Hosting'
            )
            ->assertSee(
                'Approve Attribution'
            );

        $this->assertNull(
            $newItem
                ->refresh()
                ->client_service_id
        );

        $this->actingAs($user)
            ->post(
                route(
                    'reconciliation.commercial.attribution.approve',
                    [
                        'clientId' => $client->id,
                        'candidateFingerprint' => $attribution
                            ->candidateFingerprint,
                    ]
                )
            )
            ->assertRedirect();

        $this->assertNotNull(
            $newItem
                ->refresh()
                ->client_service_id
        );

        $this->assertSame(
            'approved',
            ClientServiceAttributionReview::firstOrFail()
                ->decision
        );
    }

    private function readyAssessment(
        Client $client
    ) {
        return app(
            ClientServiceCandidateAssessmentService::class
        )
            ->forClient(
                $client,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->firstOrFail(
                fn ($row) => $row
                    ->promotionReadiness
                    === 'ready_for_review'
            );
    }

    private function invoiceItem(
        Client $client,
        string $date,
        string $description,
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
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'net_amount' => $amount,
        ]);
    }
}
