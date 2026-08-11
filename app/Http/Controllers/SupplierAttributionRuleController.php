<?php

namespace App\Http\Controllers;

use App\Models\SupplierAttributionRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierAttributionRuleController extends Controller
{
    public function index(): View
    {
        return view(
            'suppliers.rules.index',
            [
                'rules' => SupplierAttributionRule::query()
                    ->with([
                        'supplier',
                        'client',
                    ])
                    ->latest()
                    ->get(),
            ]
        );
    }

    public function toggle(
        SupplierAttributionRule $rule
    ): RedirectResponse {
        $rule->update([
            'active' => ! $rule->active,
        ]);

        return back();
    }

    public function destroy(
        SupplierAttributionRule $rule
    ): RedirectResponse {
        $rule->delete();

        return back();
    }
}
