<?php

namespace App\Http\Controllers;

use App\Domains\BusinessBrain\Actions\ExecutiveActionAttentionService;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\BusinessBrain\Signals\CeoSignalCurrentAnswerService;
use App\Domains\Dashboard\Services\MorningBriefService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        MorningBriefService $brief,
        CfoBriefService $cfo,
        ExecutiveActionAttentionService $actions,
        CeoSignalCurrentAnswerService $ceoAnswers
    ): View {
        return view('dashboard', [
            'brief' => $brief->build(),

            'cfo' => $cfo->current(),

            'executiveActions' => $actions
                ->current(5),

            'ceoSignalAnswers' => $ceoAnswers
                ->current(
                    userId: $request->user()->id,

                    limit: 5
                ),
        ]);
    }
}
