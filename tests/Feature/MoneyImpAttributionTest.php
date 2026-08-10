<?php

namespace Tests\Feature;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Models\AccountingBill;
use App\Models\AccountingBillItem;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ClientAssetAllocation;
use App\Models\CostAllocation;
use App\Models\PaymentAllocation;
use App\Models\Provider;
use App\Models\ProviderAsset;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyImpAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_asset_can_be_attributed_to_a_client(): void
    {
        $watersEdge = Client::create([
            'name' => 'Waters Edge',
        ]);

        $nameCom = Provider::create([
            'name' => 'Name.com',
            'slug' => 'name-com',
        ]);

        $domain = ProviderAsset::create([
            'provider_id' => $nameCom->id,
            'asset_type' => 'domain',
            'name' => 'shed25.com',
            'current_cost' => 18.47,
            'currency' => 'GBP',
        ]);

        ClientAssetAllocation::create([
            'provider_asset_id' => $domain->id,
            'client_id' => $watersEdge->id,
            'billing_treatment' => 'rechargeable',
            'client_charge' => 45.00,
            'currency' => 'GBP',
        ]);

        $allocation = $domain->clientAllocations()->first();

        $this->assertNotNull($allocation);
        $this->assertSame('Waters Edge', $allocation->client->name);
        $this->assertSame('45.00', $allocation->client_charge);
        $this->assertSame('18.47', $domain->current_cost);
    }

    public function test_freelancer_bill_item_can_be_allocated_to_a_client(): void
    {
        $client = Client::create([
            'name' => 'Waters Edge',
        ]);

        $freelancer = Supplier::create([
            'name' => 'Freelance Developer',
            'type' => 'freelancer',
        ]);

        $bill = AccountingBill::create([
            'supplier_id' => $freelancer->id,
            'bill_number' => 'FREELANCE-1042',
            'status' => 'outstanding',
            'gross_amount' => 600,
            'outstanding_amount' => 600,
        ]);

        $item = AccountingBillItem::create([
            'accounting_bill_id' => $bill->id,
            'description' => 'Shed 25 development work',
            'quantity' => 1,
            'unit_cost' => 600,
            'net_amount' => 600,
            'gross_amount' => 600,
        ]);

        CostAllocation::create([
            'cost_allocatable_type' => AccountingBillItem::class,
            'cost_allocatable_id' => $item->id,
            'client_id' => $client->id,
            'amount' => 600,
            'currency' => 'GBP',
            'allocation_type' => 'client',
            'allocation_percent' => 100,
        ]);

        $allocation = $item->costAllocations()->first();

        $this->assertNotNull($allocation);
        $this->assertSame('Waters Edge', $allocation->client->name);
        $this->assertSame('600.00', $allocation->amount);
    }

    public function test_bank_payment_can_clear_an_invoice_operationally(): void
    {
        $client = Client::create([
            'name' => 'MML Law',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-2417',
            'status' => 'outstanding',
            'gross_amount' => 1800,
            'outstanding_amount' => 1800,
        ]);

        $bankAccount = BankAccount::create([
            'name' => 'Purple Imp Current Account',
            'bank_name' => 'Test Bank',
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $bankAccount->id,
            'client_id' => $client->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 1800,
            'description' => 'MML LAW LLP',
            'counterparty_name' => 'MML LAW LLP',
            'match_status' => 'client_matched',
            'match_confidence' => 100,
            'source_type' => 'csv',
            'transaction_hash' => hash(
                'sha256',
                'test-bank|'.now()->toDateString().'|1800.00|MML LAW LLP'
            ),
        ]);

        PaymentAllocation::create([
            'bank_transaction_id' => $transaction->id,
            'accounting_invoice_id' => $invoice->id,
            'amount' => 1800,
            'status' => 'approved',
            'confidence' => 100,
            'match_method' => 'exact_amount_and_client',
        ]);

        $balance = app(InvoiceBalanceService::class)->outstanding($invoice);

        $this->assertSame('MML Law', $transaction->client->name);
        $this->assertSame('1800.00', $transaction->paymentAllocations->first()->amount);
        $this->assertSame('0.00', $balance);
    }
}
