<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessCoverageSummaryService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCoverageSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_coverage_summary_identifies_missing_truth(): void
    {
        Client::factory()->create([
            'status' => 'active',
        ]);

        $summary =
            app(
                BusinessCoverageSummaryService::class
            )->current();

        $this->assertSame(
            1,
            $summary->clientCount
        );

        $this->assertSame(
            1,
            $summary->clientsWithoutWorkLogs
        );

        $this->assertSame(
            1,
            $summary->clientsWithoutServices
        );
    }

    public function test_provisional_client_mapping_does_not_hide_missing_bank_truth(): void
    {
        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        $account =
            BankAccount::create([
                'name' => 'Business Current Account',
                'account_type' => 'StandardBankAccount',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        BankTransaction::create([
            'bank_account_id' => $account->id,
            'client_id' => $client->id,
            'transaction_date' => '2026-09-01',
            'amount' => 1200,
            'description' => 'PROVISIONAL CLIENT PAYMENT',
            'transaction_type' => 'customer_payment',
            'match_status' => 'suggested',
            'match_confidence' => 100,
            'source_type' => 'freeagent',
            'transaction_hash' => hash(
                'sha256',
                'summary-provisional-bank-coverage'
            ),
        ]);

        $summary =
            app(
                BusinessCoverageSummaryService::class
            )->current();

        $this->assertSame(
            1,
            $summary->clientCount
        );

        $this->assertSame(
            1,
            $summary->clientsWithoutBankTransactions
        );

        $this->assertSame(
            0,
            $summary->averageCoverageConfidence
        );
    }

}
