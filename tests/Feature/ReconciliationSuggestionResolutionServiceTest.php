<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\InvoicePaymentTruthService;
use App\Domains\Reconciliation\Resolution\ReconciliationSuggestionResolutionService;
use App\Domains\Reconciliation\Services\ReconciliationCandidateService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReconciliationSuggestionResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_corroboration_is_not_approved_or_suggested_invoice_truth(): void
    {
        [
            $allocation,
            ,
            $invoice,
            $transaction,
            $user,
        ] =
            $this->fixture(
                invoiceNumber: '844',
                amount: 360,
                invoiceStatus: 'paid',
                paidAmount: 360,
                outstandingAmount: 0,
                description: 'CLIENT LTD 844'
            );

        $resolved =
            app(
                ReconciliationSuggestionResolutionService::class
            )->resolveHistorical(
                allocation: $allocation,

                invoice: $invoice,

                userId: $user->id
            );

        $this->assertSame(
            PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,
            $resolved->status
        );

        $this->assertNull(
            $resolved->approved_at
        );

        $this->assertNull(
            $resolved->approved_by
        );

        /*
         * Recording historical invoice corroboration deliberately
         * does not promote the bank transaction itself into an
         * approved invoice allocation.
         */
        $this->assertSame(
            'suggested',
            $transaction
                ->fresh()
                ->match_status
        );

        $truth =
            app(
                InvoicePaymentTruthService::class
            )->forInvoice(
                $invoice->fresh()
            );

        $this->assertSame(
            0.0,
            $truth->bankConfirmedPaid
        );

        $this->assertSame(
            0.0,
            $truth->suggestedPaid
        );

        $this->assertSame(
            0,
            $truth->approvedPaymentCount
        );

        $this->assertSame(
            0,
            $truth->suggestedPaymentCount
        );

        $this->assertSame(
            'unpaid',
            $truth->status
        );

        $this->assertTrue(
            $truth->accountingConflict
        );

        $this->assertSame(
            'bank_invoice_reference_amount_source_paid',
            data_get(
                $resolved->metadata,
                'historical_corroboration.evidence_basis'
            )
        );
    }

    public function test_historical_classification_requires_reference_amount_and_source_paid_for_strong_candidate(): void
    {
        [
            $allocation,
        ] =
            $this->fixture(
                invoiceNumber: '1511',
                amount: 60,
                invoiceStatus: 'paid',
                paidAmount: 60,
                outstandingAmount: 0,
                description: 'MINDING KIDS 1511'
            );

        $classification =
            app(
                ReconciliationSuggestionResolutionService::class
            )->historicalClassification(
                $allocation
            );

        $this->assertSame(
            'historical_corroboration_candidate',
            $classification[
                'classification'
            ]
        );

        $this->assertTrue(
            $classification[
                'explicit_reference'
            ]
        );

        $this->assertTrue(
            $classification[
                'amount_matches'
            ]
        );

        $this->assertTrue(
            $classification[
                'source_paid'
            ]
        );
    }

    public function test_paid_same_amount_invoice_without_reference_requires_human_historical_review(): void
    {
        [
            $allocation,
        ] =
            $this->fixture(
                invoiceNumber: '189',
                amount: 1820,
                invoiceStatus: 'paid',
                paidAmount: 1820,
                outstandingAmount: 0,
                description: 'INTECHO LIMITED'
            );

        $classification =
            app(
                ReconciliationSuggestionResolutionService::class
            )->historicalClassification(
                $allocation
            );

        $this->assertSame(
            'historical_review_required',
            $classification[
                'classification'
            ]
        );

        $this->assertFalse(
            $classification[
                'explicit_reference'
            ]
        );

        $this->assertTrue(
            $classification[
                'amount_matches'
            ]
        );

        $this->assertTrue(
            $classification[
                'source_paid'
            ]
        );
    }

    public function test_recurring_receipt_can_be_retargeted_to_paid_historical_invoice_without_touching_old_invoice_truth(): void
    {
        [
            $allocation,
            $client,
            $wrongInvoice,
            $transaction,
            $user,
        ] =
            $this->fixture(
                invoiceNumber: 'OLD',
                amount: 60,
                invoiceStatus: 'overdue',
                paidAmount: 0,
                outstandingAmount: 60,
                description: 'CLIENT LTD',
                transactionDate: '2026-01-30'
            );

        $historical =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => '1809',

                'status' => 'paid',

                'invoice_date' => '2026-01-29',

                'gross_amount' => 60,

                'paid_amount' => 60,

                'outstanding_amount' => 0,
            ]);

        $resolved =
            app(
                ReconciliationSuggestionResolutionService::class
            )->resolveHistorical(
                allocation: $allocation,

                invoice: $historical,

                userId: $user->id
            );

        $this->assertSame(
            'rejected',
            $allocation
                ->fresh()
                ->status
        );

        $this->assertSame(
            PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,
            $resolved->status
        );

        $this->assertSame(
            $historical->id,
            $resolved
                ->accounting_invoice_id
        );

        $this->assertSame(
            '60.00',
            $wrongInvoice
                ->fresh()
                ->outstanding_amount
        );

        $this->assertSame(
            0,
            PaymentAllocation::query()
                ->whereIn(
                    'status',
                    [
                        'approved',
                        'imported',
                    ]
                )
                ->count()
        );

        $this->assertSame(
            'suggested',
            $transaction
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            $historical->id,
            data_get(
                $allocation
                    ->fresh()
                    ->metadata,
                'resolution_superseded.target_invoice_id'
            )
        );

        $this->assertSame(
            $allocation->id,
            data_get(
                $resolved->metadata,
                'historical_corroboration.original_suggestion_id'
            )
        );
    }

    public function test_recurring_receipt_can_be_retargeted_to_exact_outstanding_invoice_as_human_approved_allocation(): void
    {
        [
            $allocation,
            $client,
            $wrongInvoice,
            $transaction,
            $user,
        ] =
            $this->fixture(
                invoiceNumber: 'OLD',
                amount: 90,
                invoiceStatus: 'overdue',
                paidAmount: 0,
                outstandingAmount: 90,
                description: 'CLIENT LTD',
                transactionDate: '2026-03-30'
            );

        $correct =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => '1934',

                'status' => 'overdue',

                'invoice_date' => '2026-03-27',

                'gross_amount' => 90,

                'paid_amount' => 0,

                'outstanding_amount' => 90,
            ]);

        $resolved =
            app(
                ReconciliationSuggestionResolutionService::class
            )->resolveApproved(
                allocation: $allocation,

                invoice: $correct,

                userId: $user->id
            );

        $this->assertSame(
            'rejected',
            $allocation
                ->fresh()
                ->status
        );

        $this->assertSame(
            'approved',
            $resolved->status
        );

        $this->assertSame(
            'manual_recurring_resolution',
            $resolved->match_method
        );

        $this->assertSame(
            $correct->id,
            $resolved
                ->accounting_invoice_id
        );

        $this->assertSame(
            'reconciled',
            $transaction
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            '90.00',
            $wrongInvoice
                ->fresh()
                ->outstanding_amount
        );

        $truth =
            app(
                InvoicePaymentTruthService::class
            )->forInvoice(
                $correct->fresh()
            );

        $this->assertSame(
            90.0,
            $truth->bankConfirmedPaid
        );

        $this->assertSame(
            1,
            $truth->approvedPaymentCount
        );

        $this->assertSame(
            'paid',
            $truth->status
        );
    }

    public function test_resolution_candidates_are_ordered_by_date_proximity_without_becoming_truth(): void
    {
        [
            $allocation,
            $client,
        ] =
            $this->fixture(
                invoiceNumber: 'OLD',
                amount: 60,
                invoiceStatus: 'overdue',
                paidAmount: 0,
                outstandingAmount: 60,
                description: 'CLIENT LTD',
                transactionDate: '2026-03-06'
            );

        $nearest =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => '1860',

                'status' => 'paid',

                'invoice_date' => '2026-02-24',

                'gross_amount' => 60,

                'paid_amount' => 60,

                'outstanding_amount' => 0,
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => '1757',

            'status' => 'paid',

            'invoice_date' => '2025-12-29',

            'gross_amount' => 60,

            'paid_amount' => 60,

            'outstanding_amount' => 0,
        ]);

        $candidates =
            app(
                ReconciliationSuggestionResolutionService::class
            )->candidates(
                $allocation
            );

        $this->assertSame(
            $nearest->id,
            $candidates
                ->first()[
                    'invoice'
                ]
                    ->id
        );

        $this->assertSame(
            10.0,
            $candidates
                ->first()[
                    'days_from_receipt'
                ]
        );

        $this->assertTrue(
            $candidates
                ->first()[
                    'historical_eligible'
                ]
        );

        /*
         * Merely projecting candidate invoices changes nothing.
         */
        $this->assertSame(
            'suggested',
            $allocation
                ->fresh()
                ->status
        );
    }

    public function test_resolution_rejects_invoice_from_another_client(): void
    {
        [
            $allocation,
            ,
            ,
            ,
            $user,
        ] =
            $this->fixture(
                invoiceNumber: 'ONE',
                amount: 60,
                invoiceStatus: 'paid',
                paidAmount: 60,
                outstandingAmount: 0,
                description: 'CLIENT LTD ONE'
            );

        $otherClient =
            Client::factory()->create([
                'name' => 'Other Client',
            ]);

        $otherInvoice =
            AccountingInvoice::create([
                'client_id' => $otherClient->id,

                'invoice_number' => 'OTHER',

                'status' => 'paid',

                'gross_amount' => 60,

                'paid_amount' => 60,

                'outstanding_amount' => 0,
            ]);

        $this->expectException(
            ValidationException::class
        );

        app(
            ReconciliationSuggestionResolutionService::class
        )->resolveHistorical(
            allocation: $allocation,

            invoice: $otherInvoice,

            userId: $user->id
        );
    }

    public function test_candidate_generation_skips_transaction_with_historical_corroboration_even_if_an_outstanding_same_value_invoice_exists(): void
    {
        [
            $allocation,
            $client,
            $invoice,
            $transaction,
            $user,
        ] =
            $this->fixture(
                invoiceNumber: '575',
                amount: 360,
                invoiceStatus: 'paid',
                paidAmount: 360,
                outstandingAmount: 0,
                description: 'CLIENT LTD 575'
            );

        app(
            ReconciliationSuggestionResolutionService::class
        )->resolveHistorical(
            allocation: $allocation,

            invoice: $invoice,

            userId: $user->id
        );

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'NEW-OUTSTANDING',

            'status' => 'overdue',

            'invoice_date' => '2026-02-01',

            'gross_amount' => 360,

            'paid_amount' => 0,

            'outstanding_amount' => 360,
        ]);

        /*
         * Simulate a later lifecycle/reset putting the transaction
         * back into the candidate-generator population.
         */
        $transaction->update([
            'match_status' => 'unmatched',
        ]);

        app(
            ReconciliationCandidateService::class
        )->generate(
            publishEvidence: false
        );

        $this->assertSame(
            'unmatched',
            $transaction
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,
            $allocation
                ->fresh()
                ->status
        );

        $this->assertSame(
            0,
            PaymentAllocation::query()
                ->where(
                    'status',
                    'suggested'
                )
                ->count()
        );
    }

    private function fixture(
        string $invoiceNumber,
        float $amount,
        string $invoiceStatus,
        float $paidAmount,
        float $outstandingAmount,
        string $description,
        string $transactionDate = '2026-01-30'
    ): array {
        $user =
            User::factory()->create();

        $client =
            Client::factory()->create([
                'name' => 'Client Ltd',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',

                'account_type' => 'StandardBankAccount',

                'currency' => 'GBP',

                'status' => 'active',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => $invoiceNumber,

                'status' => $invoiceStatus,

                'invoice_date' => '2025-11-25',

                'gross_amount' => $amount,

                'paid_amount' => $paidAmount,

                'outstanding_amount' => $outstandingAmount,
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => $transactionDate,

                'amount' => $amount,

                'description' => $description,

                'transaction_type' => 'customer_payment',

                'match_status' => 'suggested',

                'match_confidence' => 100,

                'source_type' => 'freeagent',

                'transaction_hash' => hash(
                    'sha256',
                    implode(
                        '|',
                        [
                            $invoiceNumber,
                            $transactionDate,
                            $description,
                        ]
                    )
                ),
            ]);

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => $amount,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',
            ]);

        return [
            $allocation,
            $client,
            $invoice,
            $transaction,
            $user,
        ];
    }
}
