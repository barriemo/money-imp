<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use App\Domains\BusinessBrain\BusinessState\BusinessState;

class BusinessStateBaselineFactory
{
    public function fromState(
        BusinessState $state
    ): BusinessStateBaseline {
        $financial =
            $state->financial;

        $cash =
            $financial->cash;

        $receivables =
            $financial->receivables;

        $liabilities =
            $financial->liabilities;

        $revenue =
            $state->revenue;

        return new BusinessStateBaseline(
            metrics: collect([
                $this->metric(
                    domain: 'cash',

                    metric: BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,

                    source: 'financial.cash.safeAvailableCash',

                    known: $cash->safeAvailableCash !== null,

                    value: $cash->safeAvailableCash
                ),

                $this->metric(
                    domain: 'financial',

                    metric: BusinessStateMetricCatalog::KNOWN_NET_POSITION,

                    source: 'financial.cash.knownNetPosition',

                    known: true,

                    value: $cash->knownNetPosition
                ),

                $this->metric(
                    domain: 'receivables',

                    metric: BusinessStateMetricCatalog::LEDGER_OUTSTANDING_RECEIVABLES,

                    source: 'financial.receivables.ledgerOutstanding',

                    known: true,

                    value: $receivables->ledgerOutstanding
                ),

                $this->metric(
                    domain: 'receivables',

                    metric: BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION,

                    source: 'financial.receivables.paymentsWaitingAllocation',

                    known: true,

                    value: $receivables->paymentsWaitingAllocation
                ),

                $this->metric(
                    domain: 'receivables',

                    metric: BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,

                    source: 'financial.receivables.verifiedCollectible',

                    known: $receivables->verifiedCollectible
                        !== null,

                    value: $receivables->verifiedCollectible
                ),

                /*
                 * This is explicitly the exposure established so far.
                 *
                 * It remains a valid known value even when total
                 * liability coverage is incomplete.
                 */
                $this->metric(
                    domain: 'liabilities',

                    metric: BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,

                    source: 'financial.liabilities.known',

                    known: true,

                    value: $liabilities->known
                ),

                /*
                 * The same underlying liability amount can only be
                 * called the total when the authoritative liability
                 * contract says coverage is complete.
                 */
                $this->metric(
                    domain: 'liabilities',

                    metric: BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,

                    source: 'financial.liabilities.known',

                    known: $liabilities->coverageComplete,

                    value: $liabilities->coverageComplete
                            ? $liabilities->known
                            : null
                ),

                /*
                 * RevenueTruthSummaryService constructs this count from
                 * records whose Client.status is literally "active".
                 *
                 * The metric name deliberately describes that record
                 * state rather than inferring a commercial relationship.
                 */
                $this->metric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::CLIENT_RECORDS_MARKED_ACTIVE,

                    source: 'revenue.clientCount',

                    known: true,

                    value: $revenue->clientCount
                ),

                $this->metric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::GROSS_INVOICED_REVENUE_REPRESENTED,

                    source: 'revenue.grossInvoiced',

                    known: true,

                    value: $revenue->grossInvoiced
                ),

                $this->metric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::PAID_REVENUE_ACCORDING_TO_ACCOUNTING,

                    source: 'revenue.paidAccordingToAccounting',

                    known: true,

                    value: $revenue->paidAccordingToAccounting
                ),

                $this->metric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,

                    source: 'revenue.outstanding',

                    known: true,

                    value: $revenue->outstanding
                ),

                /*
                 * This is an evidence amount, not a claim that cash
                 * physically moved during the comparison period.
                 */
                $this->metric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE,

                    source: 'revenue.bankVerifiedPaymentValue',

                    known: true,

                    value: $revenue->bankVerifiedPaymentValue
                ),

                $this->metric(
                    domain: 'commercial',

                    metric: BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE,

                    source: 'revenue.clientsWithOutstandingRevenue',

                    known: true,

                    value: $revenue->clientsWithOutstandingRevenue
                ),

                $this->metric(
                    domain: 'evidence',

                    metric: BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE,

                    source: 'revenue.clientsWithWeakPaymentEvidence',

                    known: true,

                    value: $revenue->clientsWithWeakPaymentEvidence
                ),

                /*
                 * This is limited to value established from recorded
                 * work evidence. It is not a total-unbilled-work claim.
                 */
                $this->metric(
                    domain: 'delivery',

                    metric: BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE,

                    source: 'revenue.unrecoveredWorkValue',

                    known: true,

                    value: $revenue->unrecoveredWorkValue
                ),

                $this->metric(
                    domain: 'evidence',

                    metric: BusinessStateMetricCatalog::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE,

                    source: 'revenue.clientsWithoutWorkEvidence',

                    known: true,

                    value: $revenue->clientsWithoutWorkEvidence
                ),

                $this->metric(
                    domain: 'evidence',

                    metric: BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS,

                    source: 'financial.cash.verifiedAccountCount',

                    known: true,

                    value: $cash->verifiedAccountCount
                ),

                $this->metric(
                    domain: 'evidence',

                    metric: BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,

                    source: 'financial.cash.unverifiedAccountCount',

                    known: true,

                    value: $cash->unverifiedAccountCount
                ),

                $this->metric(
                    domain: 'evidence',

                    metric: BusinessStateMetricCatalog::STALE_BANK_ACCOUNT_RECORDS,

                    source: 'financial.cash.staleAccountCount',

                    known: true,

                    value: $cash->staleAccountCount
                ),
            ]),

            asOf: $state->asOf
        );
    }

    private function metric(
        string $domain,
        string $metric,
        string $source,
        bool $known,
        int|float|null $value,
    ): BusinessStateMetric {
        return new BusinessStateMetric(
            domain: $domain,

            metric: $metric,

            scope: 'business',

            clientId: null,

            client: null,

            source: $source,

            known: $known,

            value: $value
        );
    }
}
