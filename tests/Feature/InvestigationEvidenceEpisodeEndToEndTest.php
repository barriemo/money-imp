<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Domains\BusinessBrain\Investigation\Reassessment\InvestigationReassessmentService;
use App\Domains\BusinessBrain\Investigation\Timeline\InvestigationTimelinePresenter;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationEvidenceEpisodeEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_evidence_creates_correlated_reasoning_episode_end_to_end(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $currentAccount =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $currentAccount->id,
            'client_id' => $client->id,
            'transaction_date' => '2024-03-02',
            'amount' => 90,
            'description' => 'PEAK',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'e2e-current-account'
            ),
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        $case =
            app(
                InvestigationCaseService::class
            )->open(
                type: 'client_ledger',
                title: 'Peak investigation',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $case->forceFill([
            'current_hypothesis' => 'Those invoices were paid into our old HSBC account.',
        ])->save();

        app(
            InvestigationReassessmentService::class
        )->reassess(
            $case
        );

        $initialDestination =
            $case->events()
                ->whereIn(
                    'type',
                    [
                        'claim_assessed',
                        'claim_changed',
                    ]
                )
                ->get()
                ->filter(
                    fn ($event) => ($event->payload['key'] ?? null)
                        === 'payment_destination_hsbc'
                )
                ->last();

        $this->assertNotNull(
            $initialDestination
        );

        $this->assertSame(
            'unverified',
            $initialDestination->payload['status']
        );

        $hsbc =
            BankAccount::create([
                'name' => 'HSBC Old Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'inactive',
            ]);

        BankTransaction::create([
            'bank_account_id' => $hsbc->id,
            'client_id' => $client->id,
            'transaction_date' => '2025-10-25',
            'amount' => 21990,
            'description' => 'PEAK RENEWABLES INV1686',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'e2e-hsbc-1686'
            ),
        ]);

        app(
            InvestigationEvidenceBus::class
        )->publish(
            new EvidenceChange(
                domain: 'bank',
                type: 'bank_transactions_changed',
                subjectType: 'client',
                subjectId: $client->id,
                metadata: [
                    'bank_account_id' => $hsbc->id,
                    'transaction_count' => 1,
                ]
            )
        );

        $case->refresh();

        $correlatedEvents =
            $case->events()
                ->get()
                ->filter(
                    fn ($event) => in_array(
                        $event->type,
                        [
                            'evidence_changed',
                            'claim_changed',
                            'hypothesis_changed',
                            'case_closed',
                        ],
                        true
                    )
                        && (
                            $event->payload['correlation_id']
                            ?? null
                        ) !== null
                );

        $correlationIds =
            $correlatedEvents
                ->pluck(
                    'payload.correlation_id'
                )
                ->filter()
                ->unique()
                ->values();

        $this->assertCount(
            1,
            $correlationIds
        );

        $this->assertTrue(
            $correlatedEvents->contains(
                fn ($event) => $event->type === 'evidence_changed'
            )
        );

        $this->assertTrue(
            $correlatedEvents->contains(
                fn ($event) => $event->type === 'claim_changed'
                    && (
                        $event->payload['key']
                        ?? null
                    ) === 'payment_destination_hsbc'
                    && (
                        $event->payload['previous_status']
                        ?? null
                    ) === 'unverified'
                    && (
                        $event->payload['status']
                        ?? null
                    ) === 'supported'
            )
        );

        $output =
            app(
                InvestigationTimelinePresenter::class
            )->present(
                $case
            );

        $this->assertStringContainsString(
            'Evidence episode',
            $output
        );

        $this->assertStringContainsString(
            'FreeAgent bank transaction evidence changed.',
            $output
        );

        $this->assertStringContainsString(
            'UNVERIFIED',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTED',
            $output
        );
    }
}
