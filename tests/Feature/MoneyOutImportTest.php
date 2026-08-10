<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoneyOutImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_amex_csv_can_be_previewed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $account = BankAccount::factory()->create([
            'name' => 'Amex 1',
            'account_type' => 'CreditCardAccount',
        ]);

        $csv = implode("\n", [
            'Date,Description,Amount,Reference',
            '01/08/2026,OPENAI *CHATGPT,-20.00,ABC123',
            '02/08/2026,ADOBE,-49.99,ABC124',
        ]);

        $file = UploadedFile::fake()
            ->createWithContent(
                'amex.csv',
                $csv
            );

        $response = $this
            ->actingAs($user)
            ->post(
                route('money-out.import.preview'),
                [
                    'bank_account_id' => $account->id,
                    'provider' => 'amex',
                    'statement' => $file,
                ]
            );

        $response->assertRedirect(
            route('money-out.import.index')
        );

        $response->assertSessionHas(
            'money_out_import_preview',
            fn (array $preview) => $preview['rows_seen'] === 2
                && $preview['duplicates'] === 0
                && $preview['new_rows'] === 2
        );
    }

    public function test_existing_transaction_is_shown_as_duplicate(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $account = BankAccount::factory()->create([
            'name' => 'Amex 1',
            'account_type' => 'CreditCardAccount',
        ]);

        $hash = hash(
            'sha256',
            implode('|', [
                $account->id,
                '2026-08-01',
                '-20.00',
                'openai *chatgpt',
                'ABC123',
            ])
        );

        BankTransaction::factory()->create([
            'bank_account_id' => $account->id,
            'transaction_date' => '2026-08-01',
            'amount' => -20,
            'description' => 'OPENAI *CHATGPT',
            'transaction_hash' => $hash,
        ]);

        $csv = implode("\n", [
            'Date,Description,Amount,Reference',
            '01/08/2026,OPENAI *CHATGPT,-20.00,ABC123',
        ]);

        $file = UploadedFile::fake()
            ->createWithContent(
                'amex.csv',
                $csv
            );

        $response = $this
            ->actingAs($user)
            ->post(
                route('money-out.import.preview'),
                [
                    'bank_account_id' => $account->id,
                    'provider' => 'amex',
                    'statement' => $file,
                ]
            );

        $response->assertSessionHas(
            'money_out_import_preview',
            fn (array $preview) => $preview['rows_seen'] === 1
                && $preview['duplicates'] === 1
                && $preview['new_rows'] === 0
        );
    }
}
