<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Historical\HistoricalPaymentVerificationService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalPaymentVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_historical_exact_payment_creates_suggestion_even_when_accounting_says_paid(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Historical Client',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-HIST-001',

                'status' => 'paid',

                'invoice_date' => '2026-01-01',

                'gross_amount' => 1200,

                'paid_amount' => 1200,

                'outstanding_amount' => 0,
            ]);

        $transaction =
            $this->transaction(
                clientId: $client->id,

                date: '2026-01-10',

                amount: 1200,

                description: 'HISTORICAL CLIENT'
            );

        $stats =
            app(
                HistoricalPaymentVerificationService::class
            )->generate();

        $allocation =
            PaymentAllocation::firstOrFail();

        $this->assertSame(
            1,
            $stats['created']
        );

        $this->assertSame(
            $transaction->id,
            $allocation->bank_transaction_id
        );

        $this->assertSame(
            $invoice->id,
            $allocation->accounting_invoice_id
        );

        $this->assertSame(
            'suggested',
            $allocation->status
        );

        $this->assertSame(
            'historical_client_exact_amount',
            $allocation->match_method
        );

        $this->assertSame(
            '100.00',
            $allocation->confidence
        );
    }

    public function test_multiple_same_value_invoices_are_left_ambiguous(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Ambiguous Client',
            ]);

        foreach (
            [
                'INV-HIST-101',
                'INV-HIST-102',
            ] as $number
        ) {
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => $number,

                'status' => 'paid',

                'invoice_date' => '2026-01-01',

                'gross_amount' => 1800,

                'paid_amount' => 1800,

                'outstanding_amount' => 0,
            ]);
        }

        $this->transaction(
            clientId: $client->id,

            date: '2026-01-10',

            amount: 1800,

            description: 'AMBIGUOUS CLIENT'
        );

        $stats =
            app(
                HistoricalPaymentVerificationService::class
            )->generate();

        $this->assertSame(
            1,
            $stats['ambiguous']
        );

        $this->assertSame(
            0,
            $stats['created']
        );

        $this->assertSame(
            0,
            PaymentAllocation::count()
        );
    }

    private function transaction(
        string $clientId,
        string $date,
        float $amount,
        string $description
    ): BankTransaction {
        $account =
            BankAccount::firstOrCreate(
                [
                    'name' => 'Business Current Account',
                ],
                [
                    'account_type' => 'StandardBankAccount',
                    'currency' => 'GBP',
                    'status' => 'active',
                ]
            );

        return BankTransaction::create([
            'bank_account_id' => $account->id,

            'client_id' => $clientId,

            'transaction_date' => $date,

            'amount' => $amount,

            'description' => $description,

            'transaction_type' => 'customer_payment',

            'match_status' => 'suggested',

            'match_confidence' => 100,

            'source_type' => 'rbs_pdf',

            'transaction_hash' => hash(
                'sha256',
                implode(
                    '|',
                    [
                        $clientId,
                        $date,
                        $amount,
                        $description,
                    ]
                )
            ),
        ]);
    }
}
