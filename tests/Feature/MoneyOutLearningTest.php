<?php

namespace Tests\Feature;

use App\Domains\MoneyOut\Services\MoneyOutCategorisationService;
use App\Domains\MoneyOut\Services\SupplierLearningService;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Supplier;
use App\Models\SupplierAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyOutLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_can_teach_supplier_alias(): void
    {
        $account = BankAccount::factory()->create();

        $batch = ImportBatch::factory()->create([
            'bank_account_id' => $account->id,
        ]);

        $supplier = Supplier::factory()->create([
            'name' => 'OpenAI',
        ]);

        $category = ExpenseCategory::create([
            'name' => 'Software',
            'slug' => 'software',
        ]);

        $client = Client::factory()->create();

        $row = ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'merchant' => 'OPENAI *CHATGPT',
            'description' => 'OPENAI *CHATGPT',
            'amount' => -20,
            'classification_status' => 'needs_review',
        ]);

        app(SupplierLearningService::class)->confirm(
            $row,
            $supplier,
            $category,
            $client,
            true,
            null
        );

        $row->refresh();

        $this->assertSame(
            'reviewed',
            $row->classification_status
        );

        $this->assertSame(
            $supplier->id,
            $row->supplier_id
        );

        $this->assertSame(
            $category->id,
            $row->expense_category_id
        );

        $this->assertSame(
            $client->id,
            $row->client_id
        );

        $this->assertSame(
            1,
            SupplierAlias::count()
        );
    }

    public function test_future_matching_merchant_is_suggested(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'OpenAI',
        ]);

        SupplierAlias::create([
            'supplier_id' => $supplier->id,
            'alias' => 'OPENAI *CHATGPT',
            'normalised_alias' => 'openai chatgpt',
            'confidence' => 100,
        ]);

        $row = ImportRow::factory()->create([
            'merchant' => 'OPENAI *CHATGPT',
            'description' => 'OPENAI *CHATGPT',
            'amount' => -20,
            'classification_status' => 'unclassified',
        ]);

        app(MoneyOutCategorisationService::class)
            ->categorise($row);

        $row->refresh();

        $this->assertSame(
            $supplier->id,
            $row->supplier_id
        );

        $this->assertSame(
            'suggested',
            $row->classification_status
        );

        $this->assertSame(
            '100.00',
            $row->classification_confidence
        );
    }
}
