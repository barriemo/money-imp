<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\Dashboard\Services\MorningBriefService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        MorningBriefService $morning,
        CfoBriefService $cfo
    ): View {
        return view('dashboard', [
            'brief' => $morning->build(),

            'cfo' => $cfo->current(),
        ]);
    }
}
