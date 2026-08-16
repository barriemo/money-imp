<?php

namespace App\Domains\Imports\Services;

use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use App\Domains\Suppliers\Rules\SupplierAttributionAutoApplier;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionImportService
{
    public function __construct(
        private readonly StatementParserRegistry $parsers,
        private readonly SupplierAttributionAutoApplier $supplierRules,
        private readonly InvestigationEvidenceBus $evidenceBus,
    ) {}

    public function import(
        string $path,
        string $provider,
        BankAccount $account,
        ?int $userId = null
    ): ImportBatch {
        $parser = $this->parsers->for($provider);

        $batch = ImportBatch::create([
            'source_type' => 'transaction_file',
            'provider' => strtolower($provider),
            'bank_account_id' => $account->id,
            'original_filename' => basename($path),
            'file_hash' => hash_file('sha256', $path),
            'status' => 'processing',
            'started_at' => now(),
            'created_by' => $userId,
        ]);

        try {
            $occurrences = [];

            foreach ($parser->parse($path) as $index => $source) {
                $batch->increment('rows_seen');

                $identity = implode('|', [
                    $account->id,
                    $source->date->toDateString(),
                    number_format(
                        $source->amount,
                        2,
                        '.',
                        ''
                    ),
                    strtolower(
                        trim($source->description)
                    ),
                    $source->reference ?? '',
                ]);

                $baseHash = hash(
                    'sha256',
                    $identity
                );

                $occurrences[$baseHash] =
                    ($occurrences[$baseHash] ?? 0) + 1;

                $occurrence =
                    $occurrences[$baseHash];

                /*
                 * Preserve the existing hash for the first
                 * occurrence so previously imported data still
                 * deduplicates correctly.
                 */
                $hash = $occurrence === 1
                    ? $baseHash
                    : hash(
                        'sha256',
                        $identity.'|'.$occurrence
                    );

                if (
                    BankTransaction::query()
                        ->where('transaction_hash', $hash)
                        ->exists()
                ) {
                    ImportRow::create([
                        'import_batch_id' => $batch->id,
                        'row_number' => $index + 1,
                        'transaction_date' => $source->date,
                        'amount' => $source->amount,
                        'currency' => $source->currency,
                        'description' => $source->description,
                        'merchant' => $source->merchant,
                        'reference' => $source->reference,
                        'row_hash' => $hash,
                        'status' => 'duplicate',
                        'raw_payload' => $source->raw,
                    ]);

                    $batch->increment('rows_skipped');

                    continue;
                }

                DB::transaction(
                    function () use (
                        $batch,
                        $account,
                        $source,
                        $hash,
                        $index,
                        $userId
                    ): void {
                        $transaction = BankTransaction::create([
                            'bank_account_id' => $account->id,
                            'transaction_date' => $source->date,
                            'amount' => $source->amount,
                            'description' => $source->description,
                            'match_status' => 'unmatched',
                            'source_type' => 'file_import',
                            'transaction_hash' => $hash,
                        ]);

                        $user = $userId
                            ? User::find($userId)
                            : null;

                        $this->supplierRules->apply(
                            $transaction,
                            $user
                        );

                        ImportRow::create([
                            'import_batch_id' => $batch->id,
                            'bank_transaction_id' => $transaction->id,
                            'row_number' => $index + 1,
                            'transaction_date' => $source->date,
                            'amount' => $source->amount,
                            'currency' => $source->currency,
                            'description' => $source->description,
                            'merchant' => $source->merchant,
                            'reference' => $source->reference,
                            'row_hash' => $hash,
                            'status' => 'imported',
                            'raw_payload' => $source->raw,
                        ]);
                    }
                );

                $batch->increment('rows_imported');
            }

            $batch->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'finished_at' => now(),
                'metadata' => [
                    'error' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }

        return $batch->refresh();
    }
}
