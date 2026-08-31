<?php

namespace App\Domains\Suppliers\Documents\Services;

use App\Domains\Suppliers\Documents\Parsers\EukhostInvoiceParser;
use App\Domains\Suppliers\Documents\Parsers\SupplierDocumentParser;
use App\Domains\Suppliers\Documents\Parsers\TwentyIInvoiceParser;
use App\Models\ImportBatch;
use App\Models\SupplierProfile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;

class SupplierDocumentDetectionService
{
    /**
     * @var array<int, SupplierDocumentParser>
     */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new TwentyIInvoiceParser,
            new EukhostInvoiceParser,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function detect(ImportBatch $batch): array
    {
        $supplierName = $batch->metadata['supplier'] ?? null;

        if (! $supplierName) {
            throw new RuntimeException(
                'Supplier document has no supplier.'
            );
        }

        $supplier = SupplierProfile::query()
            ->where(
                'supplier_key',
                $this->supplierKey($supplierName)
            )
            ->first();

        if (! $supplier) {
            throw new RuntimeException(
                'Supplier profile not found: '.$supplierName
            );
        }

        $parser = collect($this->parsers)
            ->first(
                fn (SupplierDocumentParser $parser): bool => $parser->supports($supplierName)
            );

        if (! $parser) {
            throw new RuntimeException(
                'No document parser for '.$supplierName
            );
        }

        if (! $batch->storage_path) {
            throw new RuntimeException(
                'Supplier document has no storage path.'
            );
        }

        $path = Storage::path($batch->storage_path);

        $text = (new Parser)
            ->parseFile($path)
            ->getText();

        return $parser->parse($text);
    }

    private function supplierKey(string $supplier): string
    {
        return match (strtolower($supplier)) {
            '20i' => '20i',
            'eukhost' => 'eukhost',
            default => strtolower($supplier),
        };
    }
}
