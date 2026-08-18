<?php

namespace App\Domains\BusinessBrain\Reasoning;

use App\Domains\BusinessBrain\Reasoning\Engines\OpportunityEngine;

class ExecutiveReasoningSummaryService
{
    public function __construct(
        private OpportunityEngine $opportunities
    ) {}

    public function current(
        int $limit = 10
    ): ExecutiveReasoningSummary {
        $items =
            $this->opportunities
                ->current();

        $quickWins =
            $items
                ->filter(
                    fn (ExecutiveReasoning $item) => $item->score >= 80
                        && $item->estimatedEffortMinutes !== null
                        && $item->estimatedEffortMinutes <= 30
                );

        $highestOpportunity =
            $items
                ->sortByDesc(
                    'estimatedFinancialImpact'
                )
                ->first();

        return new ExecutiveReasoningSummary(
            opportunityCount: $items->count(),

            knownFinancialImpact: (float) (
                $highestOpportunity?->estimatedFinancialImpact
                ?? 0
            ),

            quickWinFinancialImpact: (float) $quickWins
                ->sum(
                    fn (ExecutiveReasoning $item) => $item
                        ->estimatedFinancialImpact
                        ?? 0
                ),

            quickWinCount: $quickWins->count(),

            financialOpportunityCount: $items
                ->where(
                    'type',
                    'financial_opportunity'
                )
                ->count(),

            financialControlCount: $items
                ->where(
                    'type',
                    'financial_control'
                )
                ->count(),

            deliveryControlCount: $items
                ->where(
                    'type',
                    'delivery_control'
                )
                ->count(),

            operationalOpportunityCount: $items
                ->where(
                    'type',
                    'operational_opportunity'
                )
                ->count(),

            highestOpportunity: $highestOpportunity,

            topOpportunities: $items
                ->take(
                    $limit
                )
                ->values()
        );
    }
}
