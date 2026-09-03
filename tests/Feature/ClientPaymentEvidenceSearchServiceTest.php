<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
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
