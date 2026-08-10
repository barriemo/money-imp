<?php

namespace App\Domains\Imports\Services;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PendingStatementImportService
{
    public function __construct(
        private StatementAccountResolver $accounts,
        private TransactionImportService $transactions
    ) {}

    public function process(
        ?int $userId = null
    ): array {
        $summary = [
            'processed' => 0,
            'imported_rows' => 0,
            'duplicates' => 0,
            'needs_review' => 0,
            'failed' => 0,
        ];

        $batches = ImportBatch::query()
            ->where('source_type', 'statement')
            ->where('status', 'pending_review')
            ->oldest('created_at')
            ->get();

        foreach ($batches as $intake) {
            $provider = $intake->provider;

            if (! $provider) {
                $summary['needs_review']++;

                continue;
            }

            $account = $this->accounts->resolve(
                $provider
            );

            if (! $account) {
                $summary['needs_review']++;

                continue;
            }

            if (
                ! $intake->storage_path
                || ! Storage::exists(
                    $intake->storage_path
                )
            ) {
                $intake->update([
                    'status' => 'failed',

                    'metadata' => [
                        ...($intake->metadata ?? []),

                        'error' => 'Stored import file is missing.',
                    ],
                ]);

                $summary['failed']++;

                continue;
            }

            try {
                $transactionBatch =
                    $this->transactions->import(
                        Storage::path(
                            $intake->storage_path
                        ),
                        $provider,
                        $account,
                        $userId
                    );

                $intake->update([
                    'bank_account_id' => $account->id,

                    'status' => 'completed',

                    'rows_seen' => $transactionBatch->rows_seen,

                    'rows_imported' => $transactionBatch->rows_imported,

                    'rows_skipped' => $transactionBatch->rows_skipped,

                    'rows_failed' => $transactionBatch->rows_failed,

                    'finished_at' => now(),

                    'metadata' => [
                        ...($intake->metadata ?? []),

                        'transaction_batch_id' => $transactionBatch->id,

                        'resolved_account' => $account->name,
                    ],
                ]);

                $summary['processed']++;

                $summary['imported_rows'] +=
                    $transactionBatch->rows_imported;

                $summary['duplicates'] +=
                    $transactionBatch->rows_skipped;
            } catch (Throwable $exception) {
                $intake->update([
                    'status' => 'failed',

                    'metadata' => [
                        ...($intake->metadata ?? []),

                        'error' => $exception->getMessage(),
                    ],

                    'finished_at' => now(),
                ]);

                $summary['failed']++;
            }
        }

        return $summary;
    }
}
