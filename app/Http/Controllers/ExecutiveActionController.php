<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Models\ExecutiveAction;
use Illuminate\View\View;

class ExecutiveActionController extends Controller
{
    public function show(
        ExecutiveAction $action
    ): View {
        return view('executive-actions.show', [
            'action' => $action,
        ]);
    }

    public function index(
        ExecutiveActionService $actions
    ): View {
        return view('executive-actions.index', [
            'actions' => $actions->pending(),
        ]);
    }
}
