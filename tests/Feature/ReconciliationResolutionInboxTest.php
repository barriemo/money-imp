<?php

namespace Tests\Feature;

use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationResolutionInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_needs_care_queue_shows_explicit_resolution_choices_and_blocks_generic_approval(): void
    {
        [
            $user,
            $client,
            $account,
        ] =
            $this->base();

        $target =
            $this->invoice(
                client: $client,

                number: 'OLD',

                amount: 60,

                status: 'overdue',

                paid: 0,

                outstanding: 60,

                date: '2025-11-25'
            );

        $nearHistorical =
            $this->invoice(
                client: $client,

                number: '1809',

                amount: 60,

                status: 'paid',

                paid: 60,

                outstanding: 0,

                date: '2026-01-29'
            );

        $first =
            $this->transaction(
                account: $account,

                client: $client,

                amount: 60,

                date: '2026-01-30',

                description: 'CLIENT LTD'
            );

        $second =
            $this->transaction(
                account: $account,

                client: $client,

                amount: 60,

                date: '2026-02-28',

                description: 'CLIENT LTD SECOND'
            );

        $firstAllocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $first->id,

                'accounting_invoice_id' => $target->id,

                'amount' => 60,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',
            ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $second->id,

            'accounting_invoice_id' => $target->id,

            'amount' => 60,

            'status' => 'suggested',

            'confidence' => 100,

            'match_method' => 'client_and_exact_amount',
        ]);

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'reconciliation.index',
                    [
                        'tab' => 'ready',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Resolve recurring receipt'
            )
            ->assertSee(
                '1809'
            )
            ->assertSee(
                'Record historical match'
            )
            ->assertSee(
                'Generic approval is blocked'
            );

        /*
         * Hiding the generic button is not sufficient.
         * A direct POST must also be refused.
         */
        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.suggestions.approve',
                    $firstAllocation
                )
            )
            ->assertRedirect()
            ->assertSessionHas(
                'error'
            );

        $this->assertSame(
            'suggested',
            $firstAllocation
                ->fresh()
                ->status
        );

        $this->assertSame(
            'suggested',
            $first
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            $nearHistorical->id,
            $nearHistorical->id
        );
    }

    public function test_historical_resolution_route_records_evidence_and_moves_it_to_historical_tab_not_client_known(): void
    {
        [
            $user,
            $client,
            $account,
        ] =
            $this->base();

        $invoice =
            $this->invoice(
                client: $client,

                number: '844',

                amount: 360,

                status: 'paid',

                paid: 360,

                outstanding: 0,

                date: '2024-09-27'
            );

        $transaction =
            $this->transaction(
                account: $account,

                client: $client,

                amount: 360,

                date: '2024-09-27',

                description: 'CLIENT LTD 844'
            );

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => 360,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'canonical_client_exact_amount',
            ]);

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.suggestions.resolve-historical',
                    $allocation
                ),
                [
                    'invoice_id' => $invoice->id,
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            PaymentAllocation::STATUS_HISTORICAL_CORROBORATION,
            $allocation
                ->fresh()
                ->status
        );

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'reconciliation.index',
                    [
                        'tab' => 'historical',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                'Historical corroborating evidence.'
            )
            ->assertSee(
                'Non-canonical invoice corroboration'
            )
            ->assertSee(
                '844'
            )
            ->assertSee(
                'CLIENT LTD 844'
            );

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'reconciliation.index',
                    [
                        'tab' => 'known',
                    ]
                )
            )
            ->assertOk()
            ->assertDontSee(
                'CLIENT LTD 844'
            );
    }

    public function test_recurring_resolution_route_can_retarget_receipt_to_selected_outstanding_invoice(): void
    {
        [
            $user,
            $client,
            $account,
        ] =
            $this->base();

        $wrong =
            $this->invoice(
                client: $client,

                number: 'OLD',

                amount: 90,

                status: 'overdue',

                paid: 0,

                outstanding: 90,

                date: '2025-07-29'
            );

        $correct =
            $this->invoice(
                client: $client,

                number: '1934',

                amount: 90,

                status: 'overdue',

                paid: 0,

                outstanding: 90,

                date: '2026-03-27'
            );

        $transaction =
            $this->transaction(
                account: $account,

                client: $client,

                amount: 90,

                date: '2026-03-30',

                description: 'CLIENT LTD'
            );

        $allocation =
            PaymentAllocation::create([
                'bank_transaction_id' => $transaction->id,

                'accounting_invoice_id' => $wrong->id,

                'amount' => 90,

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'client_and_exact_amount',
            ]);

        $this->actingAs(
            $user
        )
            ->post(
                route(
                    'reconciliation.suggestions.resolve-approved',
                    $allocation
                ),
                [
                    'invoice_id' => $correct->id,
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            'rejected',
            $allocation
                ->fresh()
                ->status
        );

        $resolved =
            PaymentAllocation::query()
                ->where(
                    'bank_transaction_id',
                    $transaction->id
                )
                ->where(
                    'accounting_invoice_id',
                    $correct->id
                )
                ->sole();

        $this->assertSame(
            'approved',
            $resolved->status
        );

        $this->assertSame(
            'reconciled',
            $transaction
                ->fresh()
                ->match_status
        );

        $this->assertSame(
            '90.00',
            $wrong
                ->fresh()
                ->outstanding_amount
        );
    }

    private function base(): array
    {
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

        return [
            $user,
            $client,
            $account,
        ];
    }

    private function invoice(
        Client $client,
        string $number,
        float $amount,
        string $status,
        float $paid,
        float $outstanding,
        string $date
    ): AccountingInvoice {
        return AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => $number,

            'status' => $status,

            'invoice_date' => $date,

            'gross_amount' => $amount,

            'paid_amount' => $paid,

            'outstanding_amount' => $outstanding,
        ]);
    }

    private function transaction(
        BankAccount $account,
        Client $client,
        float $amount,
        string $date,
        string $description
    ): BankTransaction {
        return BankTransaction::create([
            'bank_account_id' => $account->id,

            'client_id' => $client->id,

            'transaction_date' => $date,

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
                        $date,
                        $description,
                    ]
                )
            ),
        ]);
    }
}
