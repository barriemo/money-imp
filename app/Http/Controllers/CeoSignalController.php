<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CeoSignalController extends Controller
{
    public function store(
        Request $request,
        CeoSignalCaptureService $signals
    ): RedirectResponse {
        $validated =
            $request->validate([
                'signal' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

        $entry =
            $signals->capture(
                submittedBy: $request->user(),

                rawInput: $validated['signal']
            );

        $routing =
            $entry->metadata[
                'routing'
            ] ?? [];

        $message =
            'Captured. Money Imp has opened an investigation. Your input remains unverified until the evidence supports a conclusion.';

        if (
            (
                $routing[
                    'status'
                ] ?? null
            ) === 'routed'
            && (
                $routing[
                    'domain'
                ] ?? null
            ) === 'client_ledger'
        ) {
            $message =
                sprintf(
                    'Captured. Money Imp matched this to %s and linked it to a client-ledger investigation. Accounting currently reports £%s outstanding; canonical customer cash currently attributed is £%s. That does not prove no payment exists — the evidence still needs reconciliation.',
                    $routing[
                        'subject_name'
                    ],
                    number_format(
                        (float) $routing[
                            'accounting_outstanding'
                        ],
                        2
                    ),
                    number_format(
                        (float) $routing[
                            'canonical_cash'
                        ],
                        2
                    )
                );
        }

        return redirect()
            ->route(
                'dashboard'
            )
            ->with(
                'ceo_signal_success',
                $message
            );
    }
}
