<?php

namespace Tests\Feature;

use App\Domains\Reconciliation\Services\PaymentAllocationApprovalService;
use App\Domains\Reconciliation\Services\ReconciliationCandidateService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_name_and_exact_invoice_amount_create_suggestion(): void
    {
        $client = Client::create([
            'name' => 'Broker Insights',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-100',
            'status' => 'overdue',
            'gross_amount' => 180,
            'outstanding_amount' => 180,
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-28',
            'amount' => 180,
            'description' => 'BROKER INSIGHTS LIBROKER INSIGHTS',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'broker-test'),
        ]);

        app(ReconciliationCandidateService::class)->generate();

        $transaction->refresh();

        $this->assertSame($client->id, $transaction->client_id);
        $this->assertSame('suggested', $transaction->match_status);
        $this->assertSame('customer_payment', $transaction->transaction_type);

        $allocation = PaymentAllocation::firstOrFail();

        $this->assertSame($invoice->id, $allocation->accounting_invoice_id);
        $this->assertSame('180.00', $allocation->amount);
        $this->assertSame('suggested', $allocation->status);
    }

    public function test_internal_transfer_is_not_matched_to_client_invoice(): void
    {
        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-20',
            'amount' => 2000,
            'description' => 'From A/C 00293009 CERULEAN DAZE STUD Via Mobile Xfer',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'transfer-test'),
        ]);

        app(ReconciliationCandidateService::class)->generate();

        $transaction->refresh();

        $this->assertNull($transaction->client_id);
        $this->assertSame('internal_transfer', $transaction->transaction_type);
        $this->assertSame('ignored', $transaction->match_status);
        $this->assertSame(0, PaymentAllocation::count());
    }

    public function test_rebuilding_candidates_is_idempotent(): void
    {
        $client = Client::create([
            'name' => 'Broker Insights',
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-200',
            'status' => 'overdue',
            'invoice_date' => '2026-05-01',
            'gross_amount' => 180,
            'outstanding_amount' => 180,
        ]);

        $account = BankAccount::create([
            'name' => 'Business Current Account',
            'account_type' => 'StandardBankAccount',
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-05-28',
            'amount' => 180,
            'description' => 'BROKER INSIGHTS',
            'match_status' => 'unmatched',
            'source_type' => 'freeagent',
            'transaction_hash' => hash('sha256', 'repeat-test'),
        ]);

        $this->artisan('money-imp:reconciliation-candidates')
            ->assertSuccessful();

        $firstSuggested = BankTransaction::where('match_status', 'suggested')->count();
        $firstAllocations = PaymentAllocation::where('status', 'suggested')->count();

        $this->artisan('money-imp:reconciliation-candidates')
            ->assertSuccessful();

        $this->assertSame(
            $firstSuggested,
            BankTransaction::where('match_status', 'suggested')->count()
        );

        $this->assertSame(
            $firstAllocations,
            PaymentAllocation::where('status', 'suggested')->count()
        );
    }

    public function test_rejected_automated_invoice_suggestion_is_not_resurrected_by_rebuild(): void
    {
        $user =
            User::factory()->create();

        $client =
            Client::create([
                'name' => 'Broker Insights',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-REJECTED',

                'status' => 'overdue',

                'invoice_date' => '2026-05-01',

                'gross_amount' => 180,

                'outstanding_amount' => 180,
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'transaction_date' => '2026-05-28',

                'amount' => 180,

                'description' => 'BROKER INSIGHTS',

                'match_status' => 'unmatched',

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'rejected-rebuild-lifecycle'
                ),
            ]);

        app(
            ReconciliationCandidateService::class
        )->generate(
            publishEvidence: false
        );

        $transaction->refresh();

        $this->assertSame(
            'suggested',
            $transaction->match_status
        );

        $this->assertSame(
            'automated_candidate',
            data_get(
                $transaction->metadata,
                'reconciliation_provenance'
            )
        );

        $allocation =
            PaymentAllocation::query()
                ->where(
                    'bank_transaction_id',
                    $transaction->id
                )
                ->where(
                    'accounting_invoice_id',
                    $invoice->id
                )
                ->sole();

        app(
            PaymentAllocationApprovalService::class
        )->reject(
            allocation: $allocation,

            userId: $user->id,

            reason: 'Wrong invoice suggestion.'
        );

        $this->assertSame(
            'rejected',
            $allocation
                ->fresh()
                ->status
        );

        $this->artisan(
            'money-imp:reconciliation-candidates'
        )->assertSuccessful();

        $transaction->refresh();
        $allocation->refresh();

        /*
         * The receipt remains available at client level,
         * but the exact rejected invoice suggestion must
         * not be manufactured again.
         */
        $this->assertSame(
            $client->id,
            $transaction->client_id
        );

        $this->assertSame(
            'suggested',
            $transaction->match_status
        );

        $this->assertSame(
            'rejected',
            $allocation->status
        );

        $this->assertSame(
            'Wrong invoice suggestion.',
            data_get(
                $allocation->metadata,
                'rejection_reason'
            )
        );

        $this->assertSame(
            0,
            PaymentAllocation::query()
                ->where(
                    'bank_transaction_id',
                    $transaction->id
                )
                ->where(
                    'status',
                    'suggested'
                )
                ->count()
        );

        $this->assertSame(
            1,
            PaymentAllocation::query()
                ->where(
                    'bank_transaction_id',
                    $transaction->id
                )
                ->where(
                    'status',
                    'rejected'
                )
                ->count()
        );
    }

    public function test_rebuild_preserves_legacy_suggestion_without_machine_provenance(): void
    {
        $client =
            Client::create([
                'name' => 'Legacy Client Ltd',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'LEGACY-100',

                'status' => 'overdue',

                'invoice_date' => '2026-05-01',

                'gross_amount' => 180,

                'outstanding_amount' => 180,
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-05-28',

                'amount' => 180,

                'description' => 'LEGACY CLIENT LTD',

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'matched_by' => null,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'legacy-suggestion-without-provenance'
                ),

                /*
                 * Deliberately no reconciliation_provenance.
                 *
                 * This mirrors the real production review queue.
                 */
                'metadata' => [],
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => 180,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',

                'reason' => 'Legacy suggestion whose ownership cannot be inferred safely.',
            ]);

        $this->artisan(
            'money-imp:reconciliation-candidates'
        )->assertSuccessful();

        $transaction->refresh();
        $allocation->refresh();

        $this->assertSame(
            'suggested',
            $allocation->status
        );

        $this->assertSame(
            'client_and_exact_amount',
            $allocation->match_method
        );

        $this->assertSame(
            $client->id,
            $transaction->client_id
        );

        $this->assertSame(
            'suggested',
            $transaction->match_status
        );

        $this->assertNull(
            data_get(
                $transaction->metadata,
                'reconciliation_provenance'
            )
        );

        $this->assertDatabaseHas(
            'payment_allocations',
            [
                'id' => $allocation->id,

                'status' => 'suggested',
            ]
        );
    }
}
