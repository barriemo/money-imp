<?php

namespace App\Domains\Suppliers\Documents\Services;

use App\Domains\Suppliers\Documents\SupplierDocumentAssetExtractor;
use App\Models\AccountingBill;
use App\Models\AccountingBillItem;
use App\Models\ImportBatch;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupplierInvoiceProcessor
{
    public function __construct(
        private readonly SupplierDocumentDetectionService $documents,
        private readonly SupplierDocumentAssetExtractor $assets,
    ) {}

    public function process(
        ImportBatch $batch
    ): AccountingBill {
        return DB::transaction(
            function () use ($batch): AccountingBill {
                $supplierName =
                    $batch->metadata['supplier']
                    ?? null;

                if (! $supplierName) {
                    throw new RuntimeException(
                        'Supplier invoice has no supplier.'
                    );
                }

                $supplier = Supplier::query()
                    ->whereRaw(
                        'LOWER(name) = ?',
                        [strtolower(trim($supplierName))]
                    )
                    ->first();

                if (! $supplier) {
                    throw new RuntimeException(
                        'Accounting supplier not found: '
                        .$supplierName
                    );
                }

                /*
                 * The import batch is the provenance anchor.
                 * Reprocessing the same document must never create
                 * a second accounting bill.
                 */
                $existing = AccountingBill::query()
                    ->where(
                        'supplier_id',
                        $supplier->id
                    )
                    ->whereJsonContains(
                        'metadata->source_import_batch_id',
                        $batch->id
                    )
                    ->first();

                if ($existing) {
                    return $existing->load('items');
                }

                $detected = $this->documents->detect($batch);

                $items = collect($detected)
                    ->filter(
                        fn (array $item): bool => isset($item['cost'])
                            && $item['cost'] !== null
                    )
                    ->values();

                if ($items->isEmpty()) {
                    throw new RuntimeException(
                        'Supplier invoice contained no priced line items.'
                    );
                }

                $gross = round(
                    (float) $items->sum(
                        fn (array $item): float => (float) $item['cost']
                    ),
                    2
                );

                $bill = AccountingBill::create([
                    'supplier_id' => $supplier->id,
                    'status' => 'draft',
                    'bill_date' => $batch->created_at?->toDateString(),
                    'currency' => 'GBP',
                    'net_amount' => $gross,
                    'tax_amount' => 0,
                    'gross_amount' => $gross,
                    'paid_amount' => 0,
                    'outstanding_amount' => $gross,
                    'metadata' => [
                        'source' => 'supplier_invoice_import',
                        'source_import_batch_id' => $batch->id,
                        'original_filename' => $batch->original_filename,
                        'line_count' => $items->count(),
                        'processing_status' => 'pending_review',
                    ],
                ]);

                foreach ($items as $item) {
                    AccountingBillItem::create([
                        'accounting_bill_id' => $bill->id,
                        'description' => $item['name'],
                        'quantity' => 1,
                        'unit_cost' => (float) $item['cost'],
                        'net_amount' => (float) $item['cost'],
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'gross_amount' => (float) $item['cost'],
                        'metadata' => [
                            'source' => 'supplier_invoice_import',
                            'asset_type' => $item['type'] ?? null,
                            'asset_key' => $item['key'] ?? null,
                            'confidence' => $item['confidence'] ?? null,
                            'parent_key' => $item['parent_key'] ?? null,
                            'source_import_batch_id' => $batch->id,
                        ],
                    ]);
                }

                /*
                 * The document has already been detected above.
                 * Persist the same result as infrastructure truth rather
                 * than parsing the invoice a second time.
                 *
                 * This runs inside the same transaction as the accounting
                 * bill, so a failure cannot leave half an invoice behind.
                 */
                $this->assets->extractDetected(
                    $batch,
                    $detected
                );

                return $bill->load('items');
            }
        );
    }
}
