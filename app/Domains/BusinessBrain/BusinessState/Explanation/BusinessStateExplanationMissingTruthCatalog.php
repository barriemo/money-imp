<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use Illuminate\Support\Collection;

class BusinessStateExplanationMissingTruthCatalog
{
    public function forEvidenceSet(
        BusinessStateExplanationEvidenceSet $set
    ): Collection {
        /*
         * Once an interpretation has explicit supporting evidence,
         * missing truth is not manufactured here.
         *
         * Contradictory evidence is already represented explicitly in
         * the evidence set and will make the final status PARTIAL.
         *
         * Future evidence adapters may introduce supported-but-incomplete
         * interpretations with explicit missing truth through a narrower
         * policy extension. This catalogue currently describes only why
         * an interpretation cannot yet be established.
         */
        if ($set->interpretation !== null) {
            return collect();
        }

        return collect(
            match ($set->observation->kind) {
                BusinessStateChange::BECAME_KNOWN => $this->becameKnown(
                    $set->observation
                ),

                BusinessStateChange::BECAME_UNKNOWN => $this->becameUnknown(
                    $set->observation
                ),

                BusinessStateChange::INCREASED,
                BusinessStateChange::DECREASED => $this->movement(
                    $set->observation
                ),

                default => $this->fallback(
                    $set->observation
                ),
            }
        )
            ->filter(
                fn (mixed $item): bool => is_string($item)
                    && trim($item) !== ''
            )
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function becameKnown(
        BusinessStateChange $change
    ): array {
        return [
            sprintf(
                'The evidence addition, verification or reconciliation event that made %s establishable is not identified.',
                $this->label(
                    $change
                )
            ),

            sprintf(
                'The fact that %s became known does not establish whether the underlying business condition itself changed.',
                $this->label(
                    $change
                )
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function becameUnknown(
        BusinessStateChange $change
    ): array {
        return match (
            $change->current->metric
        ) {
            BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH => [
                'The underlying bank or liability evidence change that caused safe available cash to become unestablished is not identified.',

                'The current Business State does not contain supported evidence establishing the reason for this truth loss.',
            ],

            BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES => [
                'The evidence change that caused verified collectible receivables to become unestablished is not identified.',

                'The current truth gap establishes that verified collectible receivables are unknown, but does not establish why they became unknown.',
            ],

            BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE => [
                'The liability-coverage change that caused total liability exposure to become unestablished is not identified.',

                'The current Business State does not contain supported evidence establishing the reason for this truth loss.',
            ],

            default => [
                sprintf(
                    'The evidence change that caused %s to become unestablished is not identified.',
                    $this->label(
                        $change
                    )
                ),
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    private function movement(
        BusinessStateChange $change
    ): array {
        return match (
            $change->current->metric
        ) {
            BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH => [
                'The contribution from underlying bank-balance evidence to the movement is not established.',

                'The contribution from liability evidence to the movement is not established.',
            ],

            BusinessStateMetricCatalog::KNOWN_NET_POSITION => [
                'The contribution from cash movement to the net-position movement is not established.',

                'The contribution from known-liability movement to the net-position movement is not established.',
            ],

            BusinessStateMetricCatalog::LEDGER_OUTSTANDING_RECEIVABLES,
            BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE => [
                'Invoice-age movement across the compared states is not established.',

                'The contribution of newly issued invoices to the movement is not established.',

                'Payment-timing movement across the compared states is not established.',

                'Credit-note, write-off or accounting-adjustment movement is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION => [
                'The payment records entering or leaving waiting-allocation status are not identified.',

                'Whether the movement came from new unmatched payments, completed allocations or reversals is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES => [
                'The receivable evidence responsible for the verified-collectible movement is not identified.',

                'The contribution from bank-backed payment evidence to the movement is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,
            BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE => [
                'The liability records or categories responsible for the movement are not identified.',

                'The contribution of newly recorded, removed or revised liability evidence is not established.',
            ],

            BusinessStateMetricCatalog::CLIENT_RECORDS_MARKED_ACTIVE => [
                'The client records whose active-status marker changed are not identified.',

                'Whether any record-status movement represents a real commercial relationship change is not established.',
            ],

            BusinessStateMetricCatalog::GROSS_INVOICED_REVENUE_REPRESENTED => [
                'The invoice records responsible for the movement are not identified.',

                'The contribution from newly issued invoices, corrections or accounting adjustments is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::PAID_REVENUE_ACCORDING_TO_ACCOUNTING => [
                'The accounting payment records responsible for the movement are not identified.',

                'The contribution from accounting corrections or payment-status changes is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE => [
                'The approved allocation or bank-evidence records responsible for the movement are not identified.',

                'Whether the movement came from new evidence, reconciliation, reversal or correction is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE => [
                'The client records entering or leaving the outstanding-revenue set are not identified.',

                'The balance movement for those client records is not established.',
            ],

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE => [
                'The client records whose payment-evidence status changed are not identified.',

                'The specific evidence additions, removals or verification changes behind the movement are not established.',
            ],

            BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE => [
                'The work-log records contributing to the movement are not identified.',

                'Whether the movement came from new recorded work, invoice linkage, correction or removal is not established.',

                'Client-level contribution to the movement is not established.',
            ],

            BusinessStateMetricCatalog::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE => [
                'The client records whose work-evidence presence changed are not identified.',

                'The work-log additions, removals or linkage changes behind the movement are not established.',
            ],

            BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS,
            BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,
            BusinessStateMetricCatalog::STALE_BANK_ACCOUNT_RECORDS => [
                'The bank account records responsible for the movement are not identified.',

                'The verification or freshness events behind the movement are not established.',
            ],

            default => $this->fallback(
                $change
            ),
        };
    }

    /**
     * @return array<int, string>
     */
    private function fallback(
        BusinessStateChange $change
    ): array {
        return [
            sprintf(
                'The record-level drivers of the %s change are not established.',
                $this->label(
                    $change
                )
            ),
        ];
    }

    private function label(
        BusinessStateChange $change
    ): string {
        return str_replace(
            '_',
            ' ',
            $change->current->metric
        );
    }
}
