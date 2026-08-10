<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkLogController extends Controller
{
    public function index(): View
    {
        return view('work-log.index', [
            'clients' => Client::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]),

            'users' => User::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]),

            'recent' => WorkLog::query()
                ->with([
                    'client',
                    'user',
                ])
                ->latest('performed_at')
                ->latest('created_at')
                ->limit(15)
                ->get(),
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'client_id' => [
                'required',
                'uuid',
                'exists:clients,id',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'description' => [
                'required',
                'string',
                'max:2000',
            ],

            'minutes' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
            ],

            'performed_at' => [
                'required',
                'date',
            ],

            'billing_hint' => [
                'required',
                'in:billable,retainer,goodwill,unsure',
            ],
        ]);

        $rate = 95.00;

        WorkLog::create([
            ...$validated,

            'commercial_status' => 'unreviewed',

            'rate_snapshot' => $rate,

            'commercial_value' => round(
                ($validated['minutes'] / 60) * $rate,
                2
            ),
        ]);

        return back()->with(
            'success',
            'Work logged.'
        );
    }
}
