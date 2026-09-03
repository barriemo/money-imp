<?php

namespace Tests\Feature;

use App\Domains\Reconciliation\Review\ReconciliationReviewPriorityService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationReviewPriorityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_reference_match_is_strong_review(): void
    {
        [$allocation] =
            $this->fixture(
                invoice: 'INV-100',
                amount: 120,
                matchMethod: 'client_and_invoice_reference',
                transactionDescription: 'CLIENT LTD INV-100'
            );

        $priority =
            app(
                ReconciliationReviewPriorityService::class
            )->forAllocation(
                $allocation
            );

        $this->assertSame(
            'strong_review',
            $priority->band
        );

        $this->assertSame(
            80,
            $priority->score
        );

        $this->assertTrue(
            $priority->actionable
        );

        $this->assertSame(
            [],
            $priority->warnings
        );
    }

    public function test_clean_exact_amount_match_is_normal_review(): void
    {
        [$allocation] =
            $this->fixture(
                invoice: 'INV-200',
                amount: 180,
                matchMethod: 'client_and_exact_amount'
            );

        $priority =
            app(
                ReconciliationReviewPriorityService::class
            )->forAllocation(
                $allocation
            );

        $this->assertSame(
            'normal_review',
            $priority->band
        );

        $this->assertSame(
            65,
            $priority->score
        );

        $this->assertTrue(
            $priority->actionable
        );
    }

    public function test_multiple_payments_targeting_same_invoice_need_care(): void
    {
        [
            $first,
            $client,
            $invoice,
            $account,
        ] =
            $this->fixture(
                invoice: 'INV-300',
                amount: 60,
                matchMethod: 'client_and_exact_amount'
            );

        $secondTransaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-02-02',

                'amount' => 60,

                'description' => 'CLIENT LTD SECOND PAYMENT',

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'priority-duplicate-second'
                ),
            ]);

        $second =
            PaymentAllocation::create([
                'bank_transaction_id' => $secondTransaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => 60,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',
            ]);

        $service =
            app(
                ReconciliationReviewPriorityService::class
            );

        $firstPriority =
            $service->forAllocation(
                $first->fresh()
            );

        $secondPriority =
            $service->forAllocation(
                $second->fresh()
            );

        $this->assertSame(
            'needs_care',
            $firstPriority->band
        );

        $this->assertSame(
            'needs_care',
            $secondPriority->band
        );

        $this->assertSame(
            35,
            $firstPriority->score
        );

        $this->assertStringContainsString(
            '2 suggested payments',
            implode(
                ' ',
                $firstPriority->warnings
            )
        );

        /*
         * Needs-care is still a human decision.
         * It is not automatically rejected or approved.
         */
        $this->assertTrue(
            $firstPriority->actionable
        );
    }

    public function test_invoice_with_no_remaining_balance_is_stale_and_not_approvable(): void
    {
        [
            $allocation,
            ,
            $invoice,
        ] =
            $this->fixture(
                invoice: 'INV-400',
                amount: 360,
                matchMethod: 'canonical_client_exact_amount'
            );

        $invoice->update([
            'outstanding_amount' => 0,
        ]);

        $priority =
            app(
                ReconciliationReviewPriorityService::class
            )->forAllocation(
                $allocation->fresh()
            );

        $this->assertSame(
            'stale',
            $priority->band
        );

        $this->assertSame(
            0,
            $priority->score
        );

        $this->assertFalse(
            $priority->actionable
        );

        $this->assertSame(
            0.0,
            $priority->effectiveApprovalAmount
        );

        $this->assertStringContainsString(
            'no remaining allocatable balance',
            implode(
                ' ',
                $priority->warnings
            )
        );
    }

    public function test_ready_queue_orders_evidence_quality_before_materiality(): void
    {
        [$exact] =
            $this->fixture(
                invoice: 'INV-500',
                amount: 1000,
                matchMethod: 'client_and_exact_amount',
                suffix: 'exact'
            );

        [$reference] =
            $this->fixture(
                invoice: 'INV-501',
                amount: 60,
                matchMethod: 'client_and_invoice_reference',
                suffix: 'reference'
            );

        $ready =
            app(
                ReconciliationReviewPriorityService::class
            )->ready();

        $this->assertSame(
            $reference->id,
            $ready
                ->first()
                ->allocation
                ->id
        );

        $this->assertSame(
            $exact->id,
            $ready
                ->skip(
                    1
                )
                ->first()
                ->allocation
                ->id
        );
    }

    public function test_priority_projection_does_not_change_allocation_truth(): void
    {
        [$allocation] =
            $this->fixture(
                invoice: 'INV-600',
                amount: 180,
                matchMethod: 'client_and_exact_amount'
            );

        app(
            ReconciliationReviewPriorityService::class
        )->forAllocation(
            $allocation
        );

        $this->assertSame(
            'suggested',
            $allocation
                ->fresh()
                ->status
        );

        $this->assertNull(
            $allocation
                ->fresh()
                ->approved_at
        );

        $this->assertNull(
            $allocation
                ->fresh()
                ->approved_by
        );
    }

    private function fixture(
        string $invoice,
        float $amount,
        string $matchMethod,
        string $transactionDescription = 'CLIENT LTD',
        string $suffix = 'default'
    ): array {
        $client =
            Client::factory()->create([
                'name' => 'Client Ltd '.$suffix,
            ]);

        $invoiceModel =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => $invoice,

                'status' => 'overdue',

                'invoice_date' => '2026-01-01',

                'due_date' => '2026-01-08',

                'gross_amount' => $amount,

                'paid_amount' => 0,

                'outstanding_amount' => $amount,
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account '.$suffix,

                'account_type' => 'StandardBankAccount',

                'currency' => 'GBP',

                'status' => 'active',
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => '2026-01-02',

                'amount' => $amount,

                'description' => $transactionDescription,

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    'priority-'
                        .$suffix
                        .'-'
                        .$invoice
                        .'-'
                        .$amount
                ),
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoiceModel->id,

                'amount' => $amount,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => $matchMethod,
            ]);

        return [
            $allocation,
            $client,
            $invoiceModel,
            $account,
            $transaction,
        ];
    }
}
