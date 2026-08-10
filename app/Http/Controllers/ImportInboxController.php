<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use Illuminate\View\View;

class ImportInboxController extends Controller
{
    public function index(): View
    {
        $batches = ImportBatch::query()
            ->with('bankAccount')
            ->latest('created_at')
            ->paginate(30);

        return view('imports.inbox', [
            'batches' => $batches,

            'summary' => [
                'total' => ImportBatch::count(),

                'completed' => ImportBatch::query()
                    ->where('status', 'completed')
                    ->count(),

                'failed' => ImportBatch::query()
                    ->where('status', 'failed')
                    ->count(),

                'rows_imported' => (int) ImportBatch::sum(
                    'rows_imported'
                ),

                'rows_skipped' => (int) ImportBatch::sum(
                    'rows_skipped'
                ),
            ],
        ]);
    }
}
