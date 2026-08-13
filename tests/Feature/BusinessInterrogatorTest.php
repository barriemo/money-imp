<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\BusinessInterrogator;
use App\Domains\BusinessBrain\Interrogation\BusinessQuestion;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessInterrogatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_can_answer_where_are_we(): void
    {
        $client =
            Client::factory()->create([
                'status' => 'active',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-001',

            'status' => 'overdue',

            'invoice_date' => now(),

            'due_date' => now()->subDays(7),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 200,

            'outstanding_amount' => 1000,
        ]);

        $answer =
            app(
                BusinessInterrogator::class
            )->ask(
                new BusinessQuestion(
                    'where are we?'
                )
            );

        $this->assertSame(
            1,
            $answer->facts['active_clients']
        );

        $this->assertSame(
            1,
            $answer->facts['invoice_count']
        );

        $this->assertSame(
            1200.0,
            $answer->facts['gross_invoiced']
        );

        $this->assertSame(
            1000.0,
            $answer->facts['outstanding']
        );
    }
}
