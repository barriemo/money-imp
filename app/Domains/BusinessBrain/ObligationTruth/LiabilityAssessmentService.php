<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

use App\Domains\BusinessBrain\BankTruth\BankEvidenceCoverage;
use App\Domains\BusinessBrain\BankTruth\BankEvidenceCoverageService;
use App\Models\Liability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class LiabilityAssessmentService
{
    public function __construct(
        private BankEvidenceCoverageService $bankEvidenceCoverage,

        private StatutorySettlementEvidenceProvider $settlementEvidence,

        private ObligationReconciliationService $reconciliation
    ) {}

    public function current(): LiabilityAssessment
    {
        return $this->assess(
            liabilities: Liability::query()
                ->where('status', 'open')
                ->get(),

            bankCoverage: $this->bankEvidenceCoverage
                ->statutoryPayments(),

            asOf: CarbonImmutable::now(),
        );
    }

    /**
     * Reported liabilities are evidence, not canonical debt.
     *
     * For each liability type, only the most recent unresolved
     * overdue source report is treated as current overdue exposure.
     * Older unresolved source reports remain visible as historical
     * evidence rather than being blindly accumulated as current debt.
     */
    public function assess(
        Collection $liabilities,
        BankEvidenceCoverage $bankCoverage,
        CarbonImmutable $asOf,
    ): LiabilityAssessment {
        $reported = $liabilities
            ->filter(
                fn (Liability $liability) => $this->isReportedEvidence(
                    $liability
                )
            )
            ->values();

        $overdue = $reported
            ->filter(
                fn (Liability $liability) => $liability->due_date !== null
                    && $liability->due_date
                        ->startOfDay()
                        ->lt(
                            $asOf->startOfDay()
                        )
            )
            ->groupBy('type')
            ->map(
                fn (Collection $items) => $items
                    ->sortByDesc(
                        fn (Liability $liability) => $liability
                            ->due_date
                            ?->timestamp
                            ?? 0
                    )
                    ->first()
            )
            ->filter()
            ->values();

        $upcoming = $reported
            ->filter(
                fn (Liability $liability) => $liability->due_date !== null
                    && $liability->due_date
                        ->startOfDay()
                        ->gte(
                            $asOf->startOfDay()
                        )
            )
            ->values();

        $currentIds = $overdue
            ->concat($upcoming)
            ->pluck('id')
            ->map(
                fn ($id) => (string) $id
            )
            ->all();

        $historical = $reported
            ->reject(
                fn (Liability $liability) => in_array(
                    (string) $liability->id,
                    $currentIds,
                    true
                )
            )
            ->values();

        $currentItems = $overdue
            ->map(
                fn (Liability $liability) => $this->item(
                    $liability,
                    'reported_overdue'
                )
            )
            ->concat(
                $upcoming->map(
                    fn (Liability $liability) => $this->item(
                        $liability,
                        'reported_upcoming'
                    )
                )
            )
            ->sortBy('due_date')
            ->values()
            ->all();

        $assessment = new LiabilityAssessment(
            reportedTotal: (float) $reported->sum('amount'),

            currentReportedExposure: (float) $overdue->sum('amount')
                + (float) $upcoming->sum('amount'),

            reportedOverdue: (float) $overdue->sum('amount'),

            reportedUpcoming: (float) $upcoming->sum('amount'),

            historicalReportedUnresolved: (float) $historical->sum('amount'),

            settlementUnresolved: (float) $overdue->sum('amount'),

            bankTransactionEvidenceCurrent: $bankCoverage
                ->transactionEvidenceCurrent,

            canInferPaymentAbsence: $bankCoverage
                ->canInferPaymentAbsence(),

            unknownCategories: $this->unknownCategories(
                $liabilities
            ),

            currentItems: $currentItems,

            settlementEvidence: $this->settlementEvidence
                ->assess()
                ->toArray(),
        );

        $settlementEvidence = new StatutorySettlementEvidence(
            categories: $assessment->settlementEvidence['categories'] ?? [],
            totalObservedAmount: (float) (
                $assessment->settlementEvidence['total_observed_amount'] ?? 0
            ),
            paymentEvidenceExists: (bool) (
                $assessment->settlementEvidence['payment_evidence_exists'] ?? false
            ),
        );

        $reconciliation = $this->reconciliation
            ->reconcile(
                $assessment,
                $settlementEvidence
            )
            ->toArray();

        return new LiabilityAssessment(
            reportedTotal: $assessment->reportedTotal,
            currentReportedExposure: $assessment->currentReportedExposure,
            reportedOverdue: $assessment->reportedOverdue,
            reportedUpcoming: $assessment->reportedUpcoming,
            historicalReportedUnresolved: $assessment->historicalReportedUnresolved,
            settlementUnresolved: $assessment->settlementUnresolved,
            bankTransactionEvidenceCurrent: $assessment->bankTransactionEvidenceCurrent,
            canInferPaymentAbsence: $assessment->canInferPaymentAbsence,
            unknownCategories: $assessment->unknownCategories,
            currentItems: $assessment->currentItems,
            settlementEvidence: $assessment->settlementEvidence,
            reconciliation: $reconciliation,
        );
    }

    private function isReportedEvidence(
        Liability $liability
    ): bool {
        if ($liability->verified) {
            return false;
        }

        $metadata =
            $liability->metadata ?? [];

        return (
            $metadata[
                'reported_by_freeagent'
            ] ?? false
        ) === true
            || $liability->source ===
                'freeagent_vat_return';
    }

    private function unknownCategories(
        Collection $liabilities
    ): array {
        $knownTypes = $liabilities
            ->pluck('type')
            ->filter()
            ->unique()
            ->values();

        return collect([
            'vat',
            'paye',
            'corporation_tax',
        ])
            ->reject(
                fn (string $type) => $knownTypes->contains($type)
            )
            ->values()
            ->all();
    }

    private function item(
        Liability $liability,
        string $assessment
    ): array {
        return [
            'id' => (string) $liability->id,

            'type' => $liability->type,

            'name' => $liability->name,

            'amount' => (float) $liability->amount,

            'due_date' => $liability
                ->due_date
                ?->toDateString(),

            'assessment' => $assessment,

            'source' => $liability->source,

            'source_confidence' => (int) $liability->confidence,

            'settlement_verified' => (
                $liability->metadata ?? []
            )['settlement_verified']
                    ?? false,
        ];
    }
}
