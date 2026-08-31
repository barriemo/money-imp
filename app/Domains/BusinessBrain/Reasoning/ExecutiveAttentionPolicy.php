<?php

namespace App\Domains\BusinessBrain\Reasoning;

final class ExecutiveAttentionPolicy
{
    public function shouldSurface(
        ExecutiveReasoning $reasoning
    ): bool {
        if ($reasoning->score >= 90) {
            return true;
        }

        if (
            in_array(
                $reasoning->type,
                [
                    'financial_opportunity',
                    'commercial_opportunity',
                    'cash_management',
                ],
                true
            )
        ) {
            return true;
        }

        if (
            in_array(
                $reasoning->type,
                [
                    'financial_control',
                    'operational_opportunity',
                ],
                true
            )
        ) {
            return $this->isMaterialControlException(
                $reasoning
            );
        }

        return false;
    }

    private function isMaterialControlException(
        ExecutiveReasoning $reasoning
    ): bool {
        return ($reasoning->estimatedFinancialImpact ?? 0) >= 10000
            || $reasoning->urgency >= 90;
    }
}
