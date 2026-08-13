<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\Position\BusinessPositionService;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\CharlieFinding;
use App\Models\CharlieReview;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_position_is_built_from_real_truth_sources(): void
    {
        $client = Client::factory()->create([
            'status' => 'active',
        ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-001',

            'status' => 'open',

            'invoice_date' => now(),

            'due_date' => now()->addDays(7),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 200,

            'outstanding_amount' => 1000,
        ]);

        $bankAccount = BankAccount::create([
            'name' => 'Main Account',

            'currency' => 'GBP',
        ]);

        BankTransaction::create([
            'bank_account_id' => $bankAccount->id,

            'client_id' => $client->id,

            'transaction_date' => now(),

            'amount' => 500,

            'currency' => 'GBP',

            'description' => 'Customer payment',

            'match_status' => 'unmatched',

            'transaction_hash' => hash(
                'sha256',
                'business-position-test-transaction'
            ),
        ]);

        $review = CharlieReview::create([
            'client_id' => $client->id,

            'reviewed_at' => now(),

            'finding_count' => 1,

            'high_priority_count' => 0,
        ]);

        CharlieFinding::create([
            'charlie_review_id' => $review->id,

            'client_id' => $client->id,

            'category' => 'knowledge_gap',

            'severity' => 'medium',

            'title' => 'Who provides their internet connection?',

            'description' => 'Connectivity ownership affects operational risk and service opportunity.',

            'suggested_action' => 'Answer this question to improve Charlie understanding.',

            'confidence' => 100,

            'priority_score' => 74,

            'status' => 'open',

            'source' => 'general',
        ]);

        $position =
            app(
                BusinessPositionService::class
            )->current();

        $this->assertSame(
            1,
            $position->clientCount
        );

        $this->assertSame(
            1,
            $position->invoiceCount
        );

        $this->assertSame(
            1200.0,
            $position->grossInvoiced
        );

        $this->assertSame(
            1000.0,
            $position->outstanding
        );

        $this->assertSame(
            1,
            $position->unmatchedBankTransactionCount
        );

        $this->assertSame(
            1,
            $position->openCharlieFindingCount
        );
    }
}
