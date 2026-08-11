<?php

namespace App\Http\Controllers;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Illuminate\View\View;

class FinancialTruthController extends Controller
{
    public function index(
        FinancialTruthService $truth
    ): View {
        return view(
            'financial-truth.index',
            [
                'truth' => $truth->build(),
            ]
        );
    }
}
