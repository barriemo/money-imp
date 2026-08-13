<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Timeline\ClientTimelineBuilder;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTimelinePaymentEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_payment_allocation_appears_in_client_timeline(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Payment Timeline Client',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-PAYMENT',

                'status' => 'paid',

                'invoice_date' => now()
                    ->subDays(10),

                'due_date' => now()
                    ->subDays(3),

                'currency' => 'GBP',

                'net_amount' => 1000,

                'tax_amount' => 200,

                'gross_amount' => 1200,

                'paid_amount' => 1200,

                'outstanding_amount' => 0,
            ]);

        $account =
            BankAccount::create([
                'name' => 'Main Account',

                'currency' => 'GBP',
            ]);

        $transaction =
            BankTransaction::create([
                'bank_account_id' => $account->id,

                'client_id' => $client->id,

                'transaction_date' => now(),

                'amount' => 1200,

                'currency' => 'GBP',

                'description' => 'Customer payment',

                'match_status' => 'matched',

                'transaction_hash' => hash(
                    'sha256',
                    'timeline-payment-test'
                ),
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,

            'accounting_invoice_id' => $invoice->id,

            'amount' => 1200,

            'status' => 'approved',

            'confidence' => 100,

            'approved_at' => now(),

            'match_method' => 'manual',
        ]);

        $timeline =
            app(
                ClientTimelineBuilder::class
            )->build(
                $client
            );

        $payment =
            $timeline->events
                ->firstWhere(
                    'type',
                    'payment'
                );

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            1200.0,
            $payment->value
        );

        $this->assertSame(
            'Payment allocated to invoice INV-PAYMENT',
            $payment->title
        );
    }
}
