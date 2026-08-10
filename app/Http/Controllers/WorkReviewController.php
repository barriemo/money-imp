<?php

namespace App\Http\Controllers;

use App\Models\WorkLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkReviewController extends Controller
{
    public function index(): View
    {
        $logs = WorkLog::query()
            ->with([
                'client',
                'user',
            ])
            ->where('commercial_status', 'unreviewed')
            ->orderBy('performed_at')
            ->orderBy('created_at')
            ->get();

        $clients = $logs
            ->groupBy('client_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'client' => $first->client,

                    'minutes' => $rows->sum(
                        'minutes'
                    ),

                    'value' => $rows->sum(
                        fn (WorkLog $log) => (float) $log->commercial_value
                    ),

                    'count' => $rows->count(),

                    'logs' => $rows,
                ];
            })
            ->sortByDesc('value')
            ->values();

        return view('work-review.index', [
            'clients' => $clients,

            'summary' => [
                'entries' => $logs->count(),

                'minutes' => $logs->sum(
                    'minutes'
                ),

                'value' => $logs->sum(
                    fn (WorkLog $log) => (float) $log->commercial_value
                ),
            ],
        ]);
    }

    public function update(
        Request $request,
        WorkLog $workLog
    ): RedirectResponse {
        $validated = $request->validate([
            'commercial_status' => [
                'required',
                'in:invoice,retainer,goodwill,internal,written_off',
            ],

            'commercial_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $workLog->update([
            'commercial_status' => $validated['commercial_status'],

            'commercial_notes' => $validated['commercial_notes'] ?? null,

            'reviewed_by' => $request->user()->id,

            'reviewed_at' => now(),
        ]);

        return back()->with(
            'success',
            'Commercial decision saved.'
        );
    }
}
