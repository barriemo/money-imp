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

        $signals->capture(
            submittedBy: $request->user(),

            rawInput: $validated['signal']
        );

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
