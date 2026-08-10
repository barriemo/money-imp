<?php

namespace App\Http\Controllers;

use App\Domains\Dashboard\Services\MorningBriefService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        MorningBriefService $brief
    ): View {
        return view('dashboard', [
            'brief' => $brief->build(),
        ]);
    }
}
