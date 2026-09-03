<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchService;
use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BusinessMemoryEntry;
use App\Models\Client;
use App\Models\InvestigationCase;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalAutomatedReconciliationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_machine_suggested_client_attribution_is_not_canonical_cash(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Machine Suggested Ltd',
            ]);

        $account =
            BankAccount::factory()->create();

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-08-01',
            'amount' => 500,
            'currency' => 'GBP',
            'description' => 'MACHINE SUGGESTED LTD',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 85,
            'matched_by' => null,
            'source_type' => 'freeagent',

            'metadata' => [
                'reconciliation_provenance' => 'automated_candidate',
            ],

            'transaction_hash' => hash(
                'sha256',
                '3g-j-machine-suggested'
            ),
        ]);

        $payments =
            app(
                CanonicalPaymentEvidenceService::class
            )->customerPayments();

        $this->assertCount(
            0,
            $payments
        );
    }

    public function test_legacy_suggested_client_payment_without_automated_provenance_remains_canonical(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Legacy Suggested Ltd',
            ]);

        $account =
            BankAccount::factory()->create();

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-08-01',
            'amount' => 500,
            'currency' => 'GBP',
            'description' => 'LEGACY SUGGESTED LTD',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'matched_by' => null,
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                '3g-j-legacy-suggested'
            ),
        ]);

        $payments =
            app(
                CanonicalPaymentEvidenceService::class
            )->customerPayments();

        $this->assertCount(
            1,
            $payments
        );

        $this->assertSame(
            $client->id,
            $payments->sole()->clientId
        );
    }

    public function test_human_suggested_client_attribution_remains_canonical_cash(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Human Confirmed Ltd',
            ]);

        $account =
            BankAccount::factory()->create();

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-08-01',
            'amount' => 500,
            'currency' => 'GBP',
            'description' => 'HUMAN CONFIRMED LTD',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'matched_by' => $user->id,
            'matched_at' => now(),
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                '3g-j-human-confirmed'
            ),
        ]);

        $payments =
            app(
                CanonicalPaymentEvidenceService::class
            )->customerPayments();

        $this->assertCount(
            1,
            $payments
        );

        $this->assertSame(
            $client->id,
            $payments->sole()->clientId
        );
    }

    public function test_rebuild_does_not_erase_human_suggested_client_attribution(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Human Attribution Ltd',
            ]);

        $account =
            BankAccount::factory()->create();

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-08-01',
                'amount' => 250,
                'currency' => 'GBP',
                'description' => 'HUMAN ATTRIBUTION LTD',
                'transaction_type' => 'customer_payment',
                'match_status' => 'suggested',
                'match_confidence' => 100,
                'matched_by' => $user->id,
                'matched_at' => now(),
                'source_type' => 'freeagent',
                'transaction_hash' => hash(
                    'sha256',
                    '3g-j-human-survives-rebuild'
                ),
            ]);

        $this->artisan(
            'money-imp:reconciliation-candidates'
        )
            ->assertSuccessful();

        $transaction->refresh();

        $this->assertSame(
            $client->id,
            $transaction->client_id
        );

        $this->assertSame(
            'suggested',
            $transaction->match_status
        );

        $this->assertSame(
            (string) $user->id,
            (string) $transaction->matched_by
        );

        $this->assertSame(
            'customer_payment',
            $transaction->transaction_type
        );
    }

    public function test_rebuild_does_not_delete_other_payment_engine_suggestions(): void
    {
        $client =
            Client::factory()->create();

        $account =
            BankAccount::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'OTHER-ENGINE-001',
                'status' => 'overdue',
                'invoice_date' => '2026-08-01',
                'currency' => 'GBP',
                'gross_amount' => 400,
                'outstanding_amount' => 400,
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-08-02',
                'amount' => 400,
                'currency' => 'GBP',
                'description' => 'OTHER ENGINE',
                'transaction_type' => 'customer_payment',
                'match_status' => 'matched',
                'source_type' => 'file_import',
                'transaction_hash' => hash(
                    'sha256',
                    '3g-j-other-engine'
                ),
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,
                'accounting_invoice_id' => $invoice->id,
                'amount' => 400,
                'status' => 'suggested',
                'confidence' => 100,
                'match_method' => 'historical_client_exact_amount',
            ]);

        $this->artisan(
            'money-imp:reconciliation-candidates'
        )
            ->assertSuccessful();

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'id' => $allocation->id,
                'status' => 'suggested',
                'match_method' => 'historical_client_exact_amount',
            ]
        );
    }

    public function test_automated_reconciliation_updates_ceo_evidence_without_promoting_suggestion_to_cash(): void
    {
        $user =
            User::factory()->create([
                'name' => 'Barrie',
            ]);

        $client =
            Client::factory()->create([
                'name' => 'VF Electrical Services Ltd',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'VF-AUTO-REC-001',
            'status' => 'overdue',
            'invoice_date' => '2026-01-01',
            'due_date' => '2026-01-08',
            'currency' => 'GBP',
            'net_amount' => 60,
            'tax_amount' => 0,
            'gross_amount' => 60,
            'paid_amount' => 0,
            'outstanding_amount' => 60,
        ]);

        $account =
            BankAccount::factory()->create([
                'name' => 'Business Current Account',
                'currency' => 'GBP',
            ]);

        $this->bank(
            account: $account,
            date: '2025-12-31',
            amount: 1,
            description: 'OPENING COVERAGE',
            unexplained: 1
        );

        $candidate =
            $this->bank(
                account: $account,
                date: '2026-01-02',
                amount: 60,
                description: 'VF ELECTRICAL SERVICES LTD',
                unexplained: 60
            );

        $entry =
            app(
                CeoSignalCaptureService::class
            )->capture(
                submittedBy: $user,
                rawInput: 'VF Electrical invoices and payments need checked.'
            );

        $case =
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->sole();

        $initial =
            $case
                ->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->sole();

        $this->assertSame(
            'supported_payment_candidate_found',
            $initial->payload['state']
        );

        $this->assertSame(
            0.0,
            (float) $initial->payload[
                'canonical_cash'
            ]
        );

        $this->artisan(
            'money-imp:reconciliation-candidates'
        )
            ->assertSuccessful();

        $candidate->refresh();

        $this->assertSame(
            $client->id,
            $candidate->client_id
        );

        $this->assertSame(
            'suggested',
            $candidate->match_status
        );

        $this->assertNull(
            $candidate->matched_by
        );

        $this->assertSame(
            'customer_payment',
            $candidate->transaction_type
        );

        $this->assertSame(
            1,
            PaymentAllocation::query()
                ->where(
                    'bank_transaction_id',
                    $candidate->id
                )
                ->where(
                    'status',
                    'suggested'
                )
                ->count()
        );

        $canonical =
            app(
                CanonicalPaymentEvidenceService::class
            )->customerPayments()
                ->where(
                    'clientId',
                    $client->id
                );

        $this->assertCount(
            0,
            $canonical
        );

        $search =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            'supported_payment_candidate_found',
            $search->state
        );

        $this->assertSame(
            0.0,
            $search->canonicalCash
        );

        $supported =
            collect(
                $search->supportedCandidates
            )->firstWhere(
                'transaction_id',
                $candidate->id
            );

        $this->assertNotNull(
            $supported
        );

        $this->assertContains(
            'machine_client_attribution_suggestion',
            $supported['reasons']
        );

        $reassessment =
            $case
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->sole();

        $this->assertSame(
            'supported_payment_candidate_found',
            $reassessment->payload[
                'previous_state'
            ]
        );

        $this->assertSame(
            'supported_payment_candidate_found',
            $reassessment->payload[
                'state'
            ]
        );

        $this->assertSame(
            0.0,
            (float) $reassessment->payload[
                'canonical_cash'
            ]
        );

        $answer =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $user->id
            )
                ->sole();

        $this->assertSame(
            'candidate_requires_verification',
            $answer->status
        );

        $this->assertSame(
            'Evidence to verify',
            $answer->statusLabel
        );

        $this->assertTruthBoundaryPreserved(
            entry: $entry,
            case: $case
        );
    }

    private function bank(
        BankAccount $account,
        string $date,
        float $amount,
        string $description,
        float $unexplained
    ): BankTransaction {
        return BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => $date,
            'amount' => $amount,
            'currency' => 'GBP',
            'description' => $description,
            'transaction_type' => 'imported',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                implode(
                    '|',
                    [
                        $account->id,
                        $date,
                        $amount,
                        $description,
                    ]
                )
            ),
            'metadata' => [
                'freeagent_full_description' => $description,

                'freeagent_unexplained_amount' => $unexplained,
            ],
        ]);
    }

    private function assertTruthBoundaryPreserved(
        BusinessMemoryEntry $entry,
        InvestigationCase $case
    ): void {
        $entry->refresh();
        $case->refresh();

        $this->assertFalse(
            $entry->verified
        );

        $this->assertSame(
            'unverified',
            $entry->metadata[
                'truth_status'
            ]
        );

        $this->assertSame(
            'open',
            $case->status
        );

        $this->assertSame(
            0,
            $case->confidence
        );

        $this->assertNull(
            $case->verdict
        );
    }
}
