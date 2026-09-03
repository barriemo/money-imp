<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Signals\CeoSignalCaptureService;
use App\Domains\BusinessBrain\Signals\CeoSignalFindingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CeoSignalController extends Controller
{
    public function store(
        Request $request,
        CeoSignalCaptureService $signals,
        CeoSignalFindingService $findings
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

        $finding =
            $findings->forEntry(
                $entry->fresh()
            );

        if ($finding) {
            return redirect()
                ->route(
                    'dashboard'
                )
                ->with(
                    'ceo_signal_finding',
                    $finding->toArray()
                );
        }

        return redirect()
            ->route(
                'dashboard'
            )
            ->with(
                'ceo_signal_success',
                'Captured. Money Imp has opened an investigation. Your input remains unverified until the evidence supports a conclusion.'
            );
    }
}
