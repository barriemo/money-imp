<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Domains\Reconciliation\Services\PaymentAllocationApprovalService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\InvestigationCase;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoSignalApprovedPaymentSemanticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_full_payment_is_reported_as_confirmed_without_calling_source_balance_debt_truth(): void
    {
        [
            $user,
            $client,
            $invoice,
            $transaction,
            $allocation,
        ] = $this->fixture(
            gross: 1200,
            sourceOutstanding: 1200,
            payment: 1200
        );

        $before =
            InvestigationCase::query()
                ->where(
                    'type',
                    'client_ledger'
                )
                ->sole();

        app(
            PaymentAllocationApprovalService::class
        )->approve(
            allocation: $allocation,

            userId: (string) $user->id
        );

        $answer =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $user->id
            )
                ->sole();

        $this->assertSame(
            'confirmed_payment_source_difference',
            $answer->status
        );

        $this->assertSame(
            'Payment confirmed / source differs',
            $answer->statusLabel
        );

        $this->assertStringContainsString(
            '£1,200.00 confirmed received',
            $answer->headline
        );

        $this->assertStringContainsString(
            '£0.00 of invoice value is not covered',
            $answer->summary
        );

        $this->assertStringContainsString(
            'accounting source currently reports £1,200.00 outstanding',
            $answer->summary
        );

        $this->assertStringContainsString(
            'not proof that the source-reported balance is still owed',
            $answer->summary
        );

        $this->assertSame(
            1,
            $before
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->count()
        );

        $event =
            $before
                ->events()
                ->where(
                    'type',
                    'payment_evidence_reassessment'
                )
                ->sole();

        $this->assertSame(
            1200.0,
            (float) $event->payload[
                'confirmed_allocated_payment'
            ]
        );

        $this->assertSame(
            0.0,
            (float) $event->payload[
                'allocation_uncovered_amount'
            ]
        );

        $this->assertSame(
            1,
            (int) $event->payload[
                'approved_payment_count'
            ]
        );

        $this->assertSame(
            1,
            (int) $event->payload[
                'source_outstanding_disagreement_count'
            ]
        );

        $this->assertSame(
            'open',
            $before->fresh()->status
        );

        $this->assertSame(
            0,
            $before->fresh()->confidence
        );

        $this->assertNull(
            $before->fresh()->verdict
        );

        $transaction->refresh();

        $this->assertSame(
            'reconciled',
            $transaction->match_status
        );

        $this->assertSame(
            'approved',
            $allocation->fresh()->status
        );

        $this->assertSame(
            1200.0,
            (float) $invoice
                ->fresh()
                ->outstanding_amount
        );

        $this->assertSame(
            $client->id,
            $transaction->client_id
        );
    }

    public function test_approved_partial_payment_separates_confirmed_receipt_from_uncovered_invoice_value(): void
    {
        [
            $user,
            ,
            ,
            ,
            $allocation,
        ] = $this->fixture(
            gross: 2000,
            sourceOutstanding: 2000,
            payment: 500
        );

        app(
            PaymentAllocationApprovalService::class
        )->approve(
            allocation: $allocation,

            userId: (string) $user->id
        );

        $answer =
            app(
                CeoSignalCurrentAnswerService::class
            )->current(
                userId: $user->id
            )
                ->sole();

        $this->assertSame(
            'confirmed_payment_source_difference',
            $answer->status
        );

        $this->assertStringContainsString(
            '£500.00 confirmed received',
            $answer->headline
        );

        $this->assertStringContainsString(
            '£1,500.00 of invoice value is not covered by approved allocation evidence',
            $answer->summary
        );

        $this->assertStringContainsString(
            'accounting source currently reports £2,000.00 outstanding',
            $answer->summary
        );

        $this->assertStringContainsString(
            '£500.00 difference',
            $answer->summary
        );

        $this->assertStringContainsString(
            'not by itself proof that no other payment occurred',
            strtolower(
                $answer->truthBoundary
            )
        );
    }

    private function fixture(
        float $gross,
        float $sourceOutstanding,
        float $payment
    ): array {
        $user =
            User::factory()->create([
                'name' => 'Barrie',
            ]);

        $client =
            Client::factory()->create([
                'name' => 'Approved Payment Client Ltd',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'APPROVED-001',
                'status' => 'overdue',
                'invoice_date' => '2026-01-01',
                'due_date' => '2026-01-08',
                'currency' => 'GBP',
                'net_amount' => $gross,
                'tax_amount' => 0,
                'gross_amount' => $gross,
                'paid_amount' => 0,
                'outstanding_amount' => $sourceOutstanding,
            ]);

        $account =
            BankAccount::factory()->create([
                'currency' => 'GBP',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2025-12-31',
            'amount' => 1,
            'currency' => 'GBP',
            'description' => 'OPENING COVERAGE',
            'transaction_type' => 'imported',
            'match_status' => 'unmatched',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                '4a-opening-'.$gross.'-'.$payment
            ),
        ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,
                'client_id' => $client->id,
                'transaction_date' => '2026-01-02',
                'amount' => $payment,
                'currency' => 'GBP',
                'description' => 'APPROVED PAYMENT CLIENT LTD',
                'transaction_type' => 'customer_payment',
                'match_status' => 'suggested',
                'match_confidence' => 100,
                'matched_by' => null,
                'source_type' => 'freeagent',
                'transaction_hash' => hash(
                    'sha256',
                    '4a-payment-'.$gross.'-'.$payment
                ),
                'metadata' => [
                    'freeagent_full_description' => 'APPROVED PAYMENT CLIENT LTD',

                    'freeagent_unexplained_amount' => $payment,

                    'reconciliation_provenance' => 'automated_candidate',
                ],
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => $payment,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',

                'reason' => 'Stage 4A semantic fixture.',
            ]);

        app(
            CeoSignalCaptureService::class
        )->capture(
            submittedBy: $user,

            rawInput: 'Approved Payment Client invoices and payments need checked.'
        );

        return [
            $user,
            $client,
            $invoice,
            $transaction,
            $allocation,
        ];
    }
}
