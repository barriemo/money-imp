<?php

namespace Tests\Feature;

use App\Domains\Imports\Services\TransactionImportService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ImportRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepeatedTransactionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_transactions_in_same_file_do_not_crash(): void
    {
        $account = BankAccount::factory()->create([
            'name' => 'Amex Test',
            'account_type' => 'CreditCardAccount',
        ]);

        $path = storage_path(
            'framework/testing/repeated-transactions.csv'
        );

        file_put_contents(
            $path,
            implode("\n", [
                'Date,Description,Amount,Reference',
                '01/08/2026,DETAYLING SERVICES,60.00,',
                '01/08/2026,DETAYLING SERVICES,60.00,',
            ])
        );

        $batch = app(
            TransactionImportService::class
        )->import(
            $path,
            'amex_csv',
            $account
        );

        $this->assertSame(
            'completed',
            $batch->status
        );

        $this->assertSame(
            2,
            $batch->rows_seen
        );

        $this->assertSame(
            2,
            $batch->rows_imported
        );

        $this->assertSame(
            0,
            $batch->rows_skipped
        );

        $this->assertSame(
            2,
            BankTransaction::where(
                'bank_account_id',
                $account->id
            )->count()
        );

        $this->assertSame(
            2,
            ImportRow::where(
                'import_batch_id',
                $batch->id
            )->count()
        );

        @unlink($path);
    }
}
