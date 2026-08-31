<?php

namespace App\Http\Controllers;

use App\Domains\Imports\Services\PendingStatementImportService;
use App\Domains\Imports\Services\UniversalImportService;
use App\Domains\Suppliers\Documents\Actions\ProcessSupplierInvoice;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UniversalImportController extends Controller
{
    public function store(
        Request $request,
        UniversalImportService $imports
    ): RedirectResponse {
        $validated = $request->validate([
            'files' => [
                'required',
                'array',
                'max:30',
            ],

            'files.*' => [
                'required',
                'file',
                'mimes:pdf,csv,txt,zip',
                'max:51200',
            ],
        ]);

        $results = [];

        foreach ($validated['files'] as $file) {
            $results = [
                ...$results,
                ...$imports->ingest(
                    $file,
                    $request->user()->id
                ),
            ];
        }

        $statements = collect($results)
            ->where('type', 'statement')
            ->count();

        $supplierInvoices = collect($results)
            ->where(
                'type',
                'supplier_invoice'
            )
            ->count();

        $unknown = collect($results)
            ->where('type', 'unknown')
            ->count();

        return redirect()
            ->route('imports.index')
            ->with(
                'success',
                count($results)
                .' file(s) received. '
                .$statements
                .' statement(s), '
                .$supplierInvoices
                .' supplier invoice(s), '
                .$unknown
                .' need review.'
            );
    }

    public function processSupplierInvoice(
        Request $request,
        ImportBatch $batch,
        ProcessSupplierInvoice $processor
    ): RedirectResponse {
        try {
            $bill = $processor->execute($batch);

            return redirect()
                ->route('imports.index')
                ->with(
                    'success',
                    'Supplier invoice processed into accounting bill '
                    .$bill->id.'.'
                );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('imports.index')
                ->withErrors($exception->getMessage());
        }
    }

    public function processStatements(
        Request $request,
        PendingStatementImportService $imports
    ): RedirectResponse {
        $summary = $imports->process(
            $request->user()->id
        );

        return redirect()
            ->route('imports.index')
            ->with(
                'success',
                $summary['processed']
                .' statement(s) processed. '
                .$summary['imported_rows']
                .' transaction(s) imported. '
                .$summary['duplicates']
                .' duplicate(s) skipped. '
                .$summary['needs_review']
                .' still need review. '
                .$summary['failed']
                .' failed.'
            );
    }
}
