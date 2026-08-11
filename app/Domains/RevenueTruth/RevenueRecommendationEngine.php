<?php

namespace App\Domains\RevenueTruth;

use App\Domains\Infrastructure\DTOs\InfrastructureBillingReconciliation;
use App\Domains\Infrastructure\Services\InfrastructureBillingReconciliationService;
use App\Models\RevenueRecommendation;
use App\Models\RevenueRecommendationEvidence;
use App\Models\SupplierAsset;
use Illuminate\Support\Collection;

class RevenueRecommendationEngine
{
    public function __construct(
        private InfrastructureBillingReconciliationService $billing,
        private RevenueConfidenceService $confidence
    ) {}

    public function recommendations(): Collection
    {
        return SupplierAsset::query()
            ->with([
                'client',
                'supplier',
            ])
            ->where(
                'active',
                true
            )
            ->where(
                'purpose',
                'client'
            )
            ->where(
                'billable',
                true
            )
            ->whereNotNull(
                'client_id'
            )
            ->get()
            ->map(
                fn (SupplierAsset $asset) => $this->recommend(
                    $asset
                )
            )
            ->filter()
            ->values();
    }

    public function recommend(
        SupplierAsset $asset
    ): ?RevenueRecommendation {
        $truth =
            $this->billing
                ->reconcile(
                    $asset
                );

        if (
            ! in_array(
                $truth->status,
                [
                    'MISSING',
                    'UNDER_RECOVERED',
                ],
                true
            )
        ) {
            return null;
        }

        $type =
            $truth->status === 'MISSING'
                ? 'missing_recovery'
                : 'under_recovery';

        $monthlyValue =
            round(
                max(
                    0,
                    $truth->monthlyGap
                ),
                2
            );

        if ($monthlyValue <= 0) {
            return null;
        }

        $confidence =
            $this->confidence
                ->fromInfrastructure(
                    $truth->confidence
                );

        $recommendation =
            RevenueRecommendation::updateOrCreate(
                [
                    'client_id' => $asset->client_id,

                    'supplier_asset_id' => $asset->id,

                    'type' => $type,

                    'status' => 'open',
                ],
                [
                    'priority' => $this->priority(
                        $truth,
                        $monthlyValue
                    ),

                    'confidence' => $confidence,

                    'title' => $this->title(
                        $asset,
                        $truth
                    ),

                    'description' => $this->description(
                        $asset,
                        $truth
                    ),

                    'recommended_action' => $truth->status === 'MISSING'
                            ? 'Review the evidence and recover client billing if appropriate.'
                            : 'Review the existing client charge and recover the identified shortfall if appropriate.',

                    'estimated_monthly_value' => $monthlyValue,

                    'estimated_annual_value' => round(
                        $monthlyValue * 12,
                        2
                    ),

                    'metadata' => [
                        'reconciliation_status' => $truth->status,

                        'monthly_cost' => $truth->monthlyCost,

                        'monthly_recovery' => $truth->monthlyRecovery,

                        'monthly_gap' => $truth->monthlyGap,

                        'coverage_percent' => $truth->coveragePercent,

                        'supplier' => $asset->supplier
                            ?->supplier_name,

                        'asset_type' => $asset->asset_type,
                    ],
                ]
            );

        $this->recordEvidence(
            $recommendation,
            $asset,
            $truth,
            $confidence
        );

        return $recommendation->fresh(
            'evidence'
        );
    }

    private function recordEvidence(
        RevenueRecommendation $recommendation,
        SupplierAsset $asset,
        InfrastructureBillingReconciliation $truth,
        int $confidence
    ): void {
        RevenueRecommendationEvidence::updateOrCreate(
            [
                'revenue_recommendation_id' => $recommendation->id,

                'type' => 'infrastructure_reconciliation',

                'reference' => $asset->id,
            ],
            [
                'summary' => sprintf(
                    '%s costs £%.2f/month, with £%.2f/month of identified client recovery.',
                    $asset->name,
                    $truth->monthlyCost,
                    $truth->monthlyRecovery
                ),

                'confidence' => $confidence,

                'metadata' => [
                    'status' => $truth->status,

                    'monthly_cost' => $truth->monthlyCost,

                    'monthly_recovery' => $truth->monthlyRecovery,

                    'monthly_gap' => $truth->monthlyGap,

                    'matched_invoice_number' => $truth
                        ->matchedInvoiceNumber,

                    'matched_invoice_date' => $truth
                        ->matchedInvoiceDate,
                ],
            ]
        );
    }

    private function title(
        SupplierAsset $asset,
        InfrastructureBillingReconciliation $truth
    ): string {
        return match ($truth->status) {
            'MISSING' => 'Recover billing for '.$asset->name,

            'UNDER_RECOVERED' => 'Review under-recovery for '.$asset->name,

            default => 'Review revenue recovery',
        };
    }

    private function description(
        SupplierAsset $asset,
        InfrastructureBillingReconciliation $truth
    ): string {
        if ($truth->status === 'MISSING') {
            return sprintf(
                'This billable client asset costs £%.2f/month and no corresponding client recovery was identified.',
                $truth->monthlyCost
            );
        }

        return sprintf(
            'This billable client asset costs £%.2f/month but only £%.2f/month of client recovery was identified, leaving a £%.2f/month gap.',
            $truth->monthlyCost,
            $truth->monthlyRecovery,
            $truth->monthlyGap
        );
    }

    private function priority(
        InfrastructureBillingReconciliation $truth,
        float $monthlyValue
    ): int {
        $base =
            $truth->status === 'MISSING'
                ? 85
                : 70;

        return min(
            100,
            $base
            + match (true) {
                $monthlyValue >= 500 => 15,
                $monthlyValue >= 250 => 10,
                $monthlyValue >= 100 => 5,
                default => 0,
            }
        );
    }
}
