<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchService;
use App\Models\AccountingBill;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\PaymentAllocation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPaymentEvidenceSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_exact_amount_collision_is_not_payment_identity(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'VF Electrical Services Ltd',

                invoiceNumber: '042',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2026-01-01',

            amount: 60,

            description: 'OTHER COMPANY LTD',

            unexplained: 60
        );

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );

        $this->assertTrue(
            $result->bankDateSpanCoversInvoices
        );

        $this->assertSame(
            1,
            $result->exactAmountCoincidenceCount
        );

        $this->assertSame(
            1,
            $result->namedOtherExactAmountCoincidenceCount
        );

        $this->assertSame(
            0,
            $result->anonymousExactAmountCoincidenceCount
        );

        $this->assertSame(
            [],
            $result->supportedCandidates
        );
    }

    public function test_direct_client_alias_can_surface_supported_candidate_without_allocating_it(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'VF Electrical Services Ltd',

                invoiceNumber: '042',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

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

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            'supported_payment_candidate_found',
            $result->state
        );

        $this->assertSame(
            1,
            $result->directAliasHitCount
        );

        $this->assertSame(
            1,
            count(
                $result->supportedCandidates
            )
        );

        $this->assertSame(
            $candidate->id,
            $result->supportedCandidates[
                0
            ][
                'transaction_id'
            ]
        );

        $this->assertSame(
            0,
            $candidate
                ->paymentAllocations()
                ->count()
        );
    }

    public function test_explicit_invoice_reference_can_surface_supported_candidate(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '1234',

                amount: 75
            );

        $account =
            BankAccount::factory()->create();

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

                date: '2026-01-03',

                amount: 75,

                description: 'PAYMENT INV 1234',

                unexplained: 75
            );

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            'supported_payment_candidate_found',
            $result->state
        );

        $this->assertSame(
            1,
            $result->explicitInvoiceReferenceHitCount
        );

        $this->assertSame(
            $candidate->id,
            $result->supportedCandidates[
                0
            ][
                'transaction_id'
            ]
        );
    }

    public function test_numeric_substring_is_not_treated_as_invoice_reference(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '123',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $this->bank(
            account: $account,

            date: '2026-01-03',

            amount: 60,

            description: 'OTHER COMPANY ABC12345XYZ',

            unexplained: 60
        );

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            0,
            $result->explicitInvoiceReferenceHitCount
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );
    }

    public function test_anonymous_unexplained_exact_amount_remains_weak_not_supported(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '123',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $this->bank(
            account: $account,

            date: '2026-01-03',

            amount: 60,

            description: '///',

            unexplained: 60
        );

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            'weak_unidentified_exact_amount_candidates',
            $result->state
        );

        $this->assertSame(
            1,
            $result->anonymousExactAmountCoincidenceCount
        );

        $this->assertSame(
            [],
            $result->supportedCandidates
        );
    }

    public function test_bank_date_span_gap_is_reported_before_payment_conclusion(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '123',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2026-02-01',

            amount: 60,

            description: 'OTHER COMPANY',

            unexplained: 60
        );

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertFalse(
            $result->bankDateSpanCoversInvoices
        );

        $this->assertSame(
            'bank_date_span_incomplete',
            $result->state
        );
    }

    public function test_freeagent_contact_person_alias_can_surface_candidate(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'VF Electrical Services Ltd',

                invoiceNumber: '042',

                amount: 60
            );

        $connection =
            ExternalConnection::create([
                'provider' => 'freeagent',

                'name' => 'Test FreeAgent',

                'status' => 'connected',
            ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,

            'recordable_type' => Client::class,

            'recordable_id' => $client->id,

            'resource_type' => 'contact',

            'external_id' => 'contact-vf',

            'payload' => [
                'organisation_name' => 'VF Electrical Services Ltd',

                'first_name' => 'Marc',

                'last_name' => 'Van der Kuyl',
            ],
        ]);

        $account =
            BankAccount::factory()->create();

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

                description: 'MARC VAN DER KUYL',

                unexplained: 60
            );

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertContains(
            'Marc Van der Kuyl',
            $result->aliases
        );

        $this->assertSame(
            'supported_payment_candidate_found',
            $result->state
        );

        $this->assertSame(
            $candidate->id,
            $result->supportedCandidates[
                0
            ][
                'transaction_id'
            ]
        );
    }

    public function test_rejected_client_allocation_does_not_hide_exact_amount_receipt_evidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '123',

                amount: 60
            );

        $invoice =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->sole();

        $account =
            BankAccount::factory()->create();

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-01',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,

            'accounting_invoice_id' => $invoice->id,

            'amount' => 60,

            'status' => 'rejected',

            'confidence' => 100,

            'match_method' => 'client_and_exact_amount',

            'metadata' => [
                'rejection_reason' => 'Wrong invoice suggestion.',
            ],
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            1,
            $result->exactAmountCoincidenceCount
        );

        $this->assertSame(
            1,
            $result->namedOtherExactAmountCoincidenceCount
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );
    }

    public function test_active_client_allocation_still_suppresses_exact_amount_coincidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '123',

                amount: 60
            );

        $invoice =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->sole();

        $account =
            BankAccount::factory()->create();

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-01',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,

            'accounting_invoice_id' => $invoice->id,

            'amount' => 60,

            'status' => 'suggested',

            'confidence' => 100,

            'match_method' => 'client_and_exact_amount',
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            0,
            $result->exactAmountCoincidenceCount
        );
    }

    public function test_rejected_supplier_allocation_does_not_hide_client_exact_amount_evidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: '123',

                amount: 60
            );

        $supplier =
            Supplier::factory()->create([
                'name' => 'Example Supplier Ltd',
            ]);

        $bill =
            AccountingBill::create([
                'supplier_id' => $supplier->id,

                'status' => 'draft',

                'bill_date' => '2026-01-01',

                'currency' => 'GBP',

                'net_amount' => 60,

                'tax_amount' => 0,

                'gross_amount' => 60,

                'paid_amount' => 0,

                'outstanding_amount' => 60,
            ]);

        $account =
            BankAccount::factory()->create();

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-01',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        $transaction
            ->supplierPaymentAllocations()
            ->create([
                'accounting_bill_id' => $bill->id,

                'amount' => 60,

                'status' => 'rejected',

                'confidence' => 100,
            ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            1,
            $result->exactAmountCoincidenceCount
        );

        $this->assertSame(
            1,
            $result->namedOtherExactAmountCoincidenceCount
        );
    }

    public function test_legacy_unattributed_suggested_client_mapping_remains_provisional_payment_evidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Legacy Client Ltd',

                invoiceNumber: 'LEGACY-SUGGESTED',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

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

                description: 'LEGACY CLIENT LTD',

                unexplained: 60
            );

        $candidate->update([
            'client_id' => $client->id,

            'transaction_type' => 'customer_payment',

            'match_status' => 'suggested',

            'match_confidence' => 100,

            'matched_by' => null,

            'matched_at' => null,
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        /*
         * Strong payer-name evidence remains useful, but an
         * unattributed legacy suggestion is not canonical cash.
         */
        $this->assertSame(
            'supported_payment_candidate_found',
            $result->state
        );

        $this->assertSame(
            1,
            $result->directAliasHitCount
        );

        $this->assertSame(
            1,
            count(
                $result->supportedCandidates
            )
        );

        $this->assertSame(
            $candidate->id,
            $result->supportedCandidates[
                0
            ][
                'transaction_id'
            ]
        );

        $this->assertSame(
            1,
            $result->exactAmountCoincidenceCount
        );
    }

    public function test_human_suggested_client_mapping_is_canonical_not_hidden_payment_evidence(): void
    {
        $user =
            User::factory()->create();

        $client =
            $this->clientWithInvoice(
                name: 'Human Client Ltd',

                invoiceNumber: 'HUMAN-SUGGESTED',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-02',

                amount: 60,

                description: 'HUMAN CLIENT LTD',

                unexplained: 60
            );

        $transaction->update([
            'client_id' => $client->id,

            'transaction_type' => 'customer_payment',

            'match_status' => 'suggested',

            'match_confidence' => 100,

            'matched_by' => $user->id,

            'matched_at' => now(),
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        /*
         * matched_by proves an attributable human client-level
         * decision. Invoice allocation may remain unresolved.
         */
        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );

        $this->assertSame(
            0,
            $result->directAliasHitCount
        );

        $this->assertSame(
            [],
            $result->supportedCandidates
        );

        $this->assertSame(
            0,
            $result->exactAmountCoincidenceCount
        );
    }

    public function test_legacy_ignored_without_provenance_remains_open_payment_evidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: 'LEGACY-IGNORE',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-02',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        $transaction->update([
            'transaction_type' => 'card_credit',

            'match_status' => 'ignored',

            'match_confidence' => 100,

            'matched_by' => null,

            'matched_at' => null,
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        /*
         * Legacy ignored state does not tell us who made the
         * decision or why. It therefore cannot, by itself,
         * erase an otherwise still-unexplained receipt.
         */
        $this->assertSame(
            1,
            $result->exactAmountCoincidenceCount
        );

        $this->assertSame(
            1,
            $result->namedOtherExactAmountCoincidenceCount
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );
    }

    public function test_human_ignored_transaction_is_closed_payment_evidence(): void
    {
        $user =
            User::factory()->create();

        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: 'HUMAN-IGNORE',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-02',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        $transaction->update([
            'transaction_type' => 'non_client_income',

            'match_status' => 'ignored',

            'match_confidence' => 100,

            'matched_by' => $user->id,

            'matched_at' => now(),
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            0,
            $result->exactAmountCoincidenceCount
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );
    }

    public function test_automated_non_client_ignore_is_closed_payment_evidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: 'AUTO-IGNORE',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $this->bank(
            account: $account,

            date: '2025-12-31',

            amount: 1,

            description: 'OPENING COVERAGE',

            unexplained: 1
        );

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-02',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        $transaction->update([
            'transaction_type' => 'internal_transfer',

            'match_status' => 'ignored',

            'match_confidence' => 100,

            'matched_by' => null,

            'matched_at' => null,

            'metadata' => array_merge(
                $transaction->metadata ?? [],
                [
                    'reconciliation_provenance' => 'automated_non_client',
                ]
            ),
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            0,
            $result->exactAmountCoincidenceCount
        );

        $this->assertSame(
            'no_supported_payment_candidate_found',
            $result->state
        );
    }

    public function test_reconciled_transaction_remains_closed_payment_evidence(): void
    {
        $client =
            $this->clientWithInvoice(
                name: 'Acme Ltd',

                invoiceNumber: 'RECONCILED',

                amount: 60
            );

        $account =
            BankAccount::factory()->create();

        $transaction =
            $this->bank(
                account: $account,

                date: '2026-01-02',

                amount: 60,

                description: 'OTHER COMPANY LTD',

                unexplained: 60
            );

        $transaction->update([
            'match_status' => 'reconciled',
        ]);

        $result =
            app(
                ClientPaymentEvidenceSearchService::class
            )->search(
                $client->id
            );

        $this->assertSame(
            0,
            $result->exactAmountCoincidenceCount
        );
    }

    private function clientWithInvoice(
        string $name,
        string $invoiceNumber,
        float $amount
    ): Client {
        $client =
            Client::factory()->create([
                'name' => $name,
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => $invoiceNumber,

            'status' => 'overdue',

            'invoice_date' => '2026-01-01',

            'due_date' => '2026-01-08',

            'currency' => 'GBP',

            'net_amount' => $amount,

            'tax_amount' => 0,

            'gross_amount' => $amount,

            'paid_amount' => 0,

            'outstanding_amount' => $amount,
        ]);

        return $client;
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
}
