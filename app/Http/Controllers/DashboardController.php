<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\Dashboard\Services\MorningBriefService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        MorningBriefService $brief,
        CfoBriefService $cfo,
        ExecutiveActionService $actions
    ): View {
        return view('dashboard', [
            'brief' => $brief->build(),

            'cfo' => $cfo->current(),

            'executiveActions' => $actions
                ->pending()
                ->take(5),
        ]);
    }
}
