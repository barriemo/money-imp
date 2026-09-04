<?php

namespace App\Domains\BusinessBrain\Attention\Change;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use Illuminate\Support\Collection;

class BusinessStateChangeAttentionPolicy
{
    public function assess(
        Collection $changes
    ): Collection {
        return $changes
            ->map(
                fn (BusinessStateChange $change) => $this->attentionFor(
                    $change
                )
            )
            ->filter()
            ->values();
    }

    private function attentionFor(
        BusinessStateChange $change
    ): ?BusinessStateChangeAttention {
        $rule =
            $this->rule(
                metric: $change->current->metric,

                kind: $change->kind
            );

        if ($rule === null) {
            return null;
        }

        return new BusinessStateChangeAttention(
            change: $change,

            type: $rule['type'],

            reason: $rule['reason']
        );
    }

    private function rule(
        string $metric,
        string $kind,
    ): ?array {
        $key =
            $metric
            .'::'
            .$kind;

        return match ($key) {
            /*
             * Truth loss.
             *
             * These are explicit authoritative values which were
             * previously established but can no longer safely be
             * stated from current evidence.
             */
            BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH
                .'::'
                .BusinessStateChange::BECAME_UNKNOWN => [
                    'type' => BusinessStateChangeAttention::TRUTH_LOST,

                    'reason' => 'Safe available cash can no longer be established from current evidence.',
                ],

            BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES
                .'::'
                .BusinessStateChange::BECAME_UNKNOWN => [
                    'type' => BusinessStateChangeAttention::TRUTH_LOST,

                    'reason' => 'Verified collectible receivables can no longer be established from current evidence.',
                ],

            BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE
                .'::'
                .BusinessStateChange::BECAME_UNKNOWN => [
                    'type' => BusinessStateChangeAttention::TRUTH_LOST,

                    'reason' => 'Total liability exposure can no longer be established from current evidence.',
                ],

            /*
             * Financial position reduced.
             *
             * The direction is factual. This policy records only
             * the observed direction and does not add a value judgement.
             */
            BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH
                .'::'
                .BusinessStateChange::DECREASED => [
                    'type' => BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED,

                    'reason' => 'Safe available cash decreased.',
                ],

            BusinessStateMetricCatalog::KNOWN_NET_POSITION
                .'::'
                .BusinessStateChange::DECREASED => [
                    'type' => BusinessStateChangeAttention::FINANCIAL_POSITION_REDUCED,

                    'reason' => 'Known net position from established evidence decreased.',
                ],

            /*
             * Explicit financial exposures.
             *
             * Ledger outstanding receivables is intentionally omitted
             * because outstanding invoiced revenue represents the same
             * condition in the current Business State and would create
             * duplicate executive attention.
             */
            BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED,

                    'reason' => 'Payments waiting allocation increased.',
                ],

            BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED,

                    'reason' => 'Known liability exposure increased.',
                ],

            BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::FINANCIAL_EXPOSURE_INCREASED,

                    'reason' => 'Outstanding invoiced revenue increased.',
                ],

            /*
             * Total liability exposure numeric movement is deliberately
             * omitted here.
             *
             * Known liability exposure already represents newly
             * established exposure without creating a duplicate signal.
             * TOTAL_LIABILITY_EXPOSURE remains important for truth-loss
             * transitions above.
             */

            /*
             * Commercial condition expansion.
             */
            BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::COMMERCIAL_CONDITION_EXPANDED,

                    'reason' => 'The number of active client records with outstanding revenue increased.',
                ],

            /*
             * Recorded work exposure.
             *
             * This remains explicitly bounded to recorded work evidence.
             */
            BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::RECORDED_WORK_EXPOSURE_INCREASED,

                    'reason' => 'Unrecovered work value established from recorded work evidence increased.',
                ],

            /*
             * Evidence coverage reductions.
             */
            BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED,

                    'reason' => 'The number of active client records with weak payment evidence increased.',
                ],

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED,

                    'reason' => 'The number of active client records without work evidence increased.',
                ],

            BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED,

                    'reason' => 'The number of unverified bank account records increased.',
                ],

            BusinessStateMetricCatalog::STALE_BANK_ACCOUNT_RECORDS
                .'::'
                .BusinessStateChange::INCREASED => [
                    'type' => BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED,

                    'reason' => 'The number of stale bank account records increased.',
                ],

            BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS
                .'::'
                .BusinessStateChange::DECREASED => [
                    'type' => BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED,

                    'reason' => 'The number of verified bank account records decreased.',
                ],

            BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE
                .'::'
                .BusinessStateChange::DECREASED => [
                    'type' => BusinessStateChangeAttention::EVIDENCE_COVERAGE_REDUCED,

                    'reason' => 'Approved bank-backed payment evidence represented in Business State decreased.',
                ],

            /*
             * Everything else remains a factual Business State change,
             * but this conservative policy does not elevate it to
             * executive attention.
             */
            default => null,
        };
    }
}
