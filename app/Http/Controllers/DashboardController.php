<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Actions\ExecutiveActionAttentionService;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\Dashboard\Services\MorningBriefService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        MorningBriefService $brief,
        CfoBriefService $cfo,
        ExecutiveActionAttentionService $actions
    ): View {
        return view('dashboard', [
            'brief' => $brief->build(),

            'cfo' => $cfo->current(),

            'executiveActions' => $actions
                ->current(5),
        ]);
    }
}
