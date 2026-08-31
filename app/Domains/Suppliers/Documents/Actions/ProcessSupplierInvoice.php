<?php

namespace App\Domains\Suppliers\Documents\Actions;

use App\Domains\Suppliers\Documents\Services\SupplierInvoiceProcessor;
use App\Models\AccountingBill;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class ProcessSupplierInvoice
{
    public function __construct(
        private readonly SupplierInvoiceProcessor $processor,
    ) {}

    public function execute(
        ImportBatch $batch
    ): AccountingBill {
        if ($batch->source_type !== 'supplier_invoice') {
            throw new \RuntimeException(
                'Import batch is not a supplier invoice.'
            );
        }

        return DB::transaction(
            function () use ($batch): AccountingBill {
                $bill = $this->processor->process($batch);

                $metadata = $batch->metadata ?? [];

                $metadata['supplier_invoice_processed'] = true;
                $metadata['accounting_bill_id'] = $bill->id;
                $metadata['processed_at'] = now()->toIso8601String();

                $batch->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'metadata' => $metadata,
                ]);

                return $bill;
            }
        );
    }
}
