<?php

namespace App\Domains\ManagedServices\Services;

use App\Domains\ManagedServices\Templates\ManagedServiceTemplateEvaluator;
use App\Models\ManagedService;

class ManagedServiceTruthService
{
    public function __construct(
        private ManagedServiceFinancialService $financials,
        private ManagedServiceTemplateEvaluator $templates
    ) {}

    public function summary(
        ManagedService $service
    ): array {
        $financial =
            $this->financials->summary(
                $service
            );

        $completeness =
            $this->templates->evaluate(
                $service
            );

        $confidence = match (true) {
            $completeness->score >= 90 => 'HIGH',

            $completeness->score >= 60 => 'MEDIUM',

            default => 'LOW',
        };

        return [
            ...$financial,

            'completeness_score' => $completeness->score,

            'financial_confidence' => $confidence,

            'missing_required' => $completeness->missing,

            'missing_recommended' => $completeness
                ->recommendedMissing,

            'margin_status' => $this->marginStatus(
                $financial[
                    'monthly_margin'
                ],
                $confidence
            ),
        ];
    }

    private function marginStatus(
        float $margin,
        string $confidence
    ): string {
        if ($confidence === 'LOW') {
            return 'PROVISIONAL';
        }

        if ($margin < 0) {
            return 'LOSS';
        }

        return 'PROFITABLE';
    }
}
