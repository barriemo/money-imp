<?php

namespace App\Http\Controllers;

use App\Domains\Imports\Parsers\AmexCsvParser;
use App\Domains\Imports\Services\TransactionImportService;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class MoneyOutImportController extends Controller
{
    public function index(): View
    {
        return view('money-out.import', [
            'accounts' => BankAccount::query()
                ->orderBy('name')
                ->get(),

            'preview' => session('money_out_import_preview'),
        ]);
    }

    public function preview(
        Request $request,
        AmexCsvParser $parser
    ): RedirectResponse {
        $validated = $request->validate([
            'bank_account_id' => [
                'required',
                'uuid',
                'exists:bank_accounts,id',
            ],

            'provider' => [
                'required',
                'in:amex',
            ],

            'statement' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240',
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['statement'];

        $storedPath = $file->store(
            'money-out/import-previews'
        );

        $absolutePath = Storage::path($storedPath);

        if (
            ! $parser->supports(
                $validated['provider'],
                strtolower($file->getClientOriginalExtension())
            )
        ) {
            throw new RuntimeException(
                'This statement format is not supported.'
            );
        }

        $account = BankAccount::findOrFail(
            $validated['bank_account_id']
        );

        $rows = collect(
            iterator_to_array(
                $parser->parse($absolutePath)
            )
        );

        $previewRows = $rows
            ->map(function ($row) use ($account) {
                $hash = hash(
                    'sha256',
                    implode('|', [
                        $account->id,
                        $row->date->toDateString(),
                        number_format(
                            $row->amount,
                            2,
                            '.',
                            ''
                        ),
                        strtolower(
                            trim($row->description)
                        ),
                        $row->reference ?? '',
                    ])
                );

                $duplicate = BankTransaction::query()
                    ->where(
                        'transaction_hash',
                        $hash
                    )
                    ->exists();

                return [
                    'date' => $row->date->toDateString(),
                    'amount' => $row->amount,
                    'description' => $row->description,
                    'reference' => $row->reference,
                    'duplicate' => $duplicate,
                ];
            });

        session()->put(
            'money_out_import_preview',
            [
                'bank_account_id' => $account->id,
                'bank_account_name' => $account->name,
                'provider' => $validated['provider'],
                'stored_path' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),

                'rows_seen' => $previewRows->count(),

                'duplicates' => $previewRows
                    ->where('duplicate', true)
                    ->count(),

                'new_rows' => $previewRows
                    ->where('duplicate', false)
                    ->count(),

                'rows' => $previewRows
                    ->take(100)
                    ->values()
                    ->all(),
            ]
        );

        return redirect()
            ->route('money-out.import.index');
    }

    public function import(
        Request $request,
        TransactionImportService $imports
    ): RedirectResponse {
        $preview = session(
            'money_out_import_preview'
        );

        if (! is_array($preview)) {
            return back()->withErrors([
                'statement' => 'No statement preview is available.',
            ]);
        }

        $account = BankAccount::findOrFail(
            $preview['bank_account_id']
        );

        $absolutePath = Storage::path(
            $preview['stored_path']
        );

        $batch = $imports->import(
            $absolutePath,
            $preview['provider'],
            $account,
            $request->user()->id
        );

        Storage::delete(
            $preview['stored_path']
        );

        session()->forget(
            'money_out_import_preview'
        );

        return redirect()
            ->route('money-out.index')
            ->with(
                'success',
                $batch->rows_imported
                    .' transaction(s) imported. '
                    .$batch->rows_skipped
                    .' duplicate(s) skipped.'
            );
    }

    public function cancel(): RedirectResponse
    {
        $preview = session(
            'money_out_import_preview'
        );

        if (
            is_array($preview)
            && isset($preview['stored_path'])
        ) {
            Storage::delete(
                $preview['stored_path']
            );
        }

        session()->forget(
            'money_out_import_preview'
        );

        return redirect()
            ->route('money-out.import.index');
    }
}
