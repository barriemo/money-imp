<?php

namespace App\Domains\Executive;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use App\Domains\OperationalTruth\Services\OperationalTruthService;
use App\Domains\RevenueTruth\RevenueTruthService;

class ExecutiveHealthReasoner
{
    public function __construct(
        private FinancialTruthService $financialTruth,
        private OperationalTruthService $operationalTruth,
        private CommercialAgreementTruthService $commercialTruth,
        private RevenueTruthService $revenueTruth
    ) {}

    public function answer(
        ExecutiveQuestion $question
    ): ExecutiveAnswer {
        if (
            $question->type
            !== 'can_keep_lights_on'
        ) {
            return new ExecutiveAnswer(
                questionType: $question->type,
                assessment: 'UNSUPPORTED',
                confidence: 0,
                summary: 'This executive question is not currently supported.'
            );
        }

        $financial =
            $this->financialTruth
                ->build();

        $operational =
            $this->operationalTruth
                ->summary();

        $commercial =
            $this->commercialTruth
                ->summary();

        $revenue =
            $this->revenueTruth
                ->summary();

        $bankConfidence =
            (int) (
                $financial['confidence']['bank_balances']
                ?? 0
            );

        $liabilityConfidence =
            (int) (
                $financial['confidence']['liabilities']
                ?? 0
            );

        $receivableConfidence =
            (int) (
                $financial['confidence']['receivables']
                ?? 0
            );

        $cashAvailable =
            $bankConfidence > 0
                ? (float) (
                    $financial['cash']['available']
                    ?? 0
                )
                : null;

        $cardDebt =
            $bankConfidence > 0
                ? (float) (
                    $financial['cash']['credit_card_debt']
                    ?? 0
                )
                : null;

        $knownLiabilities =
            $liabilityConfidence > 0
                ? (float) (
                    $financial['cash']['known_liabilities']
                    ?? 0
                )
                : null;

        $netCashPosition =
            $bankConfidence > 0
            && $liabilityConfidence > 0
                ? (float) (
                    $financial['cash']['net_position']
                    ?? 0
                )
                : null;

        $ledgerReceivables =
            (float) (
                $financial['receivables']['ledger_outstanding']
                ?? 0
            );

        $contractedMonthlyValue =
            $commercial[
                'contracted_monthly_value'
            ]
            ?? null;

        $recurringMonthly =
            $contractedMonthlyValue !== null
                ? (float) $contractedMonthlyValue
                : null;

        $contractedValueStatus =
            (string) (
                $commercial['contracted_value_status']
                ?? 'not_established'
            );

        $managedMonthlyRevenue =
            (float) (
                $operational['managed_services']['monthly_revenue']
                ?? 0
            );

        $managedMonthlyCost =
            (float) (
                $operational['managed_services']['monthly_cost']
                ?? 0
            );

        $managedMonthlyMargin =
            (float) (
                $operational['managed_services']['monthly_margin']
                ?? 0
            );

        $infrastructureMonthlyCost =
            (float) (
                $operational['infrastructure']['monthly_cost']
                ?? 0
            );

        $recoverableMonthly =
            (float) (
                $revenue['recoverable_monthly']
                ?? 0
            );

        $missingEvidence = [];

        if ($bankConfidence < 100) {
            $missingEvidence[] =
                'Not all bank and card balances are verified.';
        }

        if ($liabilityConfidence < 100) {
            $missingEvidence[] =
                'Known liabilities are not fully verified.';
        }

        if ($receivableConfidence < 100) {
            $missingEvidence[] =
                'Outstanding receivables are ledger values and are not yet verified as collectible cash.';
        }

        if (
            $contractedValueStatus
            !== 'reconciled'
        ) {
            $missingEvidence[] =
                match (
                    $contractedValueStatus
                ) {
                    'candidates_not_confirmed' => 'Possible commercial agreements exist, but none is confirmed as contracted truth.',

                    'partially_reconciled' => 'Some contracted commercial terms are confirmed while other agreement candidates remain unresolved.',

                    'partially_established' => 'Some contracted commercial terms are confirmed, but contracted-truth coverage is not yet complete.',

                    default => 'Contracted commercial terms are not yet established.',
                };
        }

        $gaps =
            $operational['gaps']
            ?? [];

        if (
            ($gaps['unallocated_assets'] ?? 0)
            > 0
        ) {
            $missingEvidence[] =
                'Some active supplier assets are not yet allocated.';
        }

        if (
            ($gaps['unknown_cost_assets'] ?? 0)
            > 0
        ) {
            $missingEvidence[] =
                'Some active supplier assets still have unknown costs.';
        }

        if (
            ($gaps['unverified_cost_allocations'] ?? 0)
            > 0
        ) {
            $missingEvidence[] =
                'Some managed-service cost allocations are unverified.';
        }

        $confidenceComponents = [
            $bankConfidence,
            $liabilityConfidence,
            $receivableConfidence,
        ];

        $confidence =
            (int) round(
                collect(
                    $confidenceComponents
                )->avg()
            );

        if (
            $missingEvidence !== []
        ) {
            $confidence =
                max(
                    0,
                    $confidence
                    - min(
                        25,
                        count(
                            $missingEvidence
                        ) * 3
                    )
                );
        }

        if (
            $netCashPosition !== null
            && $netCashPosition < 0
        ) {
            $assessment =
                'NO';

            $summary =
                'Verified cash less known debt and liabilities is currently negative.';
        } elseif (
            $missingEvidence !== []
        ) {
            $assessment =
                'INCOMPLETE';

            $summary =
                'The known position is not currently negative, but there is not yet enough verified evidence to give a dependable viability answer.';
        } else {
            $assessment =
                'YES';

            $summary =
                'The verified financial and operational position supports a positive near-term viability assessment.';
        }

        $risks = [];

        if (
            $managedMonthlyMargin < 0
        ) {
            $risks[] =
                'Known managed services are currently operating at a negative monthly margin.';
        }

        if (
            (
                $operational['infrastructure']['monthly_gap']
                ?? 0
            ) > 0
        ) {
            $risks[] =
                'Infrastructure Truth currently shows unrecovered monthly cost.';
        }

        if (
            $recoverableMonthly > 0
        ) {
            $risks[] =
                'Revenue Truth has identified recoverable monthly revenue that has not yet been actioned.';
        }

        return new ExecutiveAnswer(
            questionType: $question->type,

            assessment: $assessment,

            confidence: $confidence,

            summary: $summary,

            metrics: [
                'cash_available' => $cashAvailable,

                'credit_card_debt' => $cardDebt,

                'known_liabilities' => $knownLiabilities,

                'net_cash_position' => $netCashPosition,

                'ledger_receivables' => $ledgerReceivables,

                'recurring_monthly_equivalent' => $recurringMonthly,

                'managed_monthly_revenue' => $managedMonthlyRevenue,

                'managed_monthly_cost' => $managedMonthlyCost,

                'managed_monthly_margin' => $managedMonthlyMargin,

                'infrastructure_monthly_cost' => $infrastructureMonthlyCost,

                'recoverable_monthly' => $recoverableMonthly,
            ],

            risks: $risks,

            missingEvidence: $missingEvidence
        );
    }
}
