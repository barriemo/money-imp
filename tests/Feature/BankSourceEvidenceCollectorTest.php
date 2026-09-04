<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\BankSourceEvidenceCollector;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankSourceEvidenceCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_bank_missing_from_money_imp_is_missing_evidence(): void
    {
        $hypothesis =
            new Hypothesis(
                statement: 'Those invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: 'peak'
            );

        $evidence =
            app(
                BankSourceEvidenceCollector::class
            )->collect(
                $hypothesis
            );

        $this->assertCount(
            1,
            $evidence
        );

        $this->assertSame(
            'missing',
            $evidence[0]->position
        );

        $this->assertStringContainsString(
            'No HSBC bank account',
            $evidence[0]->description
        );
    }

    public function test_named_bank_with_account_but_no_history_is_missing_evidence(): void
    {
        BankAccount::create([
            'name' => 'HSBC Old Current Account',
            'account_type' => 'StandardBankAccount',
            'currency' => 'GBP',
            'status' => 'inactive',
        ]);

        $hypothesis =
            new Hypothesis(
                statement: 'Those invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: 'peak'
            );

        $evidence =
            app(
                BankSourceEvidenceCollector::class
            )->collect(
                $hypothesis
            );

        $this->assertSame(
            'missing',
            $evidence[0]->position
        );

        $this->assertStringContainsString(
            'no transaction history',
            strtolower(
                $evidence[0]->description
            )
        );
    }

    public function test_named_bank_provisional_client_mapping_does_not_support_destination_claim(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Provisional Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'HSBC Old Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'inactive',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1685-PROVISIONAL',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2025-10-25',
            'amount' => 21990,
            'description' => 'PROVISIONAL PEAK RENEWABLES',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'matched_by' => null,
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'provisional-peak-hsbc-1685'
            ),
        ]);

        $hypothesis =
            new Hypothesis(
                statement: 'Those invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $evidence =
            app(
                BankSourceEvidenceCollector::class
            )->collect(
                $hypothesis
            );

        $this->assertCount(
            1,
            $evidence
        );

        $this->assertSame(
            'neutral',
            $evidence[0]->position
        );

        $this->assertStringNotContainsString(
            'client-mapped payment',
            strtolower(
                $evidence[0]->description
            )
        );

        $this->assertArrayNotHasKey(
            'matching_payment_count',
            $evidence[0]->metadata
        );
    }

    public function test_named_bank_human_attributed_suggested_payment_still_supports_destination_claim(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Human Peak Renewables',
            ]);

        $user =
            User::factory()->create();

        $account =
            BankAccount::create([
                'name' => 'HSBC Old Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'inactive',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686-HUMAN',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 18750,
            'paid_amount' => 18750,
            'outstanding_amount' => 0,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2025-10-25',
            'amount' => 18750,
            'description' => 'HUMAN PEAK RENEWABLES',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'matched_by' => $user->id,
            'matched_at' => now(),
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'human-peak-hsbc-1686'
            ),
        ]);

        $hypothesis =
            new Hypothesis(
                statement: 'Those invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $evidence =
            app(
                BankSourceEvidenceCollector::class
            )->collect(
                $hypothesis
            );

        $this->assertCount(
            1,
            $evidence
        );

        $this->assertSame(
            'supports',
            $evidence[0]->position
        );

        $this->assertSame(
            95,
            $evidence[0]->confidence
        );

        $this->assertSame(
            1,
            $evidence[0]->metadata[
                'matching_payment_count'
            ]
        );

        $this->assertSame(
            18750.0,
            $evidence[0]->metadata[
                'matching_payment_value'
            ]
        );
    }

    public function test_named_bank_with_matching_client_payment_supports_destination_claim(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Peak Renewables',
            ]);

        $account =
            BankAccount::create([
                'name' => 'HSBC Old Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'inactive',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '1686',
            'invoice_date' => '2025-10-24',
            'status' => 'paid',
            'gross_amount' => 21990,
            'paid_amount' => 21990,
            'outstanding_amount' => 0,
        ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2025-10-25',
            'amount' => 21990,
            'description' => 'PEAK RENEWABLES INV1686',
            'transaction_type' => 'customer_payment',
            'source_type' => 'file_import',
            'transaction_hash' => hash(
                'sha256',
                'peak-hsbc-1686'
            ),
        ]);

        $hypothesis =
            new Hypothesis(
                statement: 'Those invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: $client->id,
                subjectName: $client->name
            );

        $evidence =
            app(
                BankSourceEvidenceCollector::class
            )->collect(
                $hypothesis
            );

        $this->assertSame(
            'supports',
            $evidence[0]->position
        );

        $this->assertSame(
            95,
            $evidence[0]->confidence
        );

        $this->assertStringContainsString(
            '£21,990.00',
            $evidence[0]->description
        );
    }
}
