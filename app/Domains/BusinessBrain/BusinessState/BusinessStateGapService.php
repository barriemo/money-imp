<?php

namespace App\Domains\BusinessBrain\BusinessState;

use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use Illuminate\Support\Collection;

class BusinessStateGapService
{
    public function assess(
        FinancialPosition $financial,
        Collection $clients
    ): BusinessStateGaps {
        $unknowns =
            collect();

        $evidenceGaps =
            collect();

        /*
         * Unknowns are claims Money Imp cannot safely establish.
         *
         * They are not adverse business conditions and they are not
         * recommendations. Existing commercial gaps remain authoritative
         * in RevenueTruthSummary.
         */
        if (
            $financial->cash
                ->safeAvailableCash === null
        ) {
            $unknowns->push(
                $this->businessGap(
                    domain: 'cash',

                    type: 'safe_available_cash_unknown',

                    title: 'Safe available cash is unknown',

                    description: 'Complete current bank and liability evidence is not available, so Money Imp cannot safely state available cash.'
                )
            );
        }

        if (
            $financial->receivables
                ->verifiedCollectible === null
        ) {
            $unknowns->push(
                $this->businessGap(
                    domain: 'receivables',

                    type: 'verified_collectible_unknown',

                    title: 'Verified collectible receivables are unknown',

                    description: 'Money Imp cannot yet establish the verified collectible value of outstanding receivables.'
                )
            );
        }

        if (
            ! $financial->liabilities
                ->coverageComplete
        ) {
            $unknownCategories =
                $financial->liabilities
                    ->unknownCategories;

            $description =
                'Liability coverage is incomplete.';

            if ($unknownCategories !== []) {
                $description .= sprintf(
                    ' Unknown categories: %s.',
                    implode(
                        ', ',
                        $unknownCategories
                    )
                );
            }

            $unknowns->push(
                $this->businessGap(
                    domain: 'liabilities',

                    type: 'liability_coverage_incomplete',

                    title: 'Total liability exposure is not fully known',

                    description: $description
                )
            );
        }

        if (
            $financial->credit
                ->facilityCount
            >
            $financial->credit
                ->verifiedFacilityCount
        ) {
            $unknowns->push(
                $this->businessGap(
                    domain: 'credit',

                    type: 'credit_verification_incomplete',

                    title: 'Verified credit exposure is incomplete',

                    description: sprintf(
                        '%d of %d active credit facilities have verified evidence.',
                        $financial->credit
                            ->verifiedFacilityCount,
                        $financial->credit
                            ->facilityCount
                    )
                )
            );
        }

        /*
         * Evidence gaps explain absent or weak source coverage.
         *
         * These do not automatically mean the underlying business state
         * is bad. They mean only that a particular evidence source is
         * absent, stale or unverified.
         */
        if (
            $financial->cash
                ->unverifiedAccountCount > 0
        ) {
            $evidenceGaps->push(
                $this->businessGap(
                    domain: 'cash',

                    type: 'unverified_bank_balance_evidence',

                    title: 'Bank balance evidence is unverified',

                    description: sprintf(
                        '%d account balance record(s) are not verified.',
                        $financial->cash
                            ->unverifiedAccountCount
                    )
                )
            );
        }

        if (
            $financial->cash
                ->staleAccountCount > 0
        ) {
            $evidenceGaps->push(
                $this->businessGap(
                    domain: 'cash',

                    type: 'stale_bank_balance_evidence',

                    title: 'Bank balance evidence is stale',

                    description: sprintf(
                        '%d verified account balance(s) are outside the current freshness window.',
                        $financial->cash
                            ->staleAccountCount
                    )
                )
            );
        }

        $clients
            ->each(
                function (ClientState $client) use (
                    $evidenceGaps
                ): void {
                    $coverage =
                        $client->coverage;

                    if (! $coverage->hasInvoices) {
                        $evidenceGaps->push(
                            $this->clientGap(
                                client: $client,

                                type: 'missing_invoice_evidence',

                                title: 'No accounting invoice evidence',

                                description: 'No accounting invoice rows are linked to this active client.'
                            )
                        );
                    }

                    if (
                        ! $coverage
                            ->hasBankTransactions
                    ) {
                        $evidenceGaps->push(
                            $this->clientGap(
                                client: $client,

                                type: 'missing_attributable_bank_evidence',

                                title: 'No attributable bank evidence',

                                description: 'No canonical attributable bank transactions are linked to this active client.'
                            )
                        );
                    }

                    if (
                        ! $coverage
                            ->hasPaymentIdentity
                    ) {
                        $evidenceGaps->push(
                            $this->clientGap(
                                client: $client,

                                type: 'missing_payment_identity',

                                title: 'No payment identity evidence',

                                description: 'No payment identity is recorded for this active client.'
                            )
                        );
                    }

                    if (! $coverage->hasWorkLogs) {
                        $evidenceGaps->push(
                            $this->clientGap(
                                client: $client,

                                type: 'missing_work_evidence',

                                title: 'No delivery evidence',

                                description: 'No work-log evidence is recorded for this active client.'
                            )
                        );
                    }

                    if (! $coverage->hasServices) {
                        $evidenceGaps->push(
                            $this->clientGap(
                                client: $client,

                                type: 'missing_service_evidence',

                                title: 'No service evidence',

                                description: 'No client-service records are linked to this active client.'
                            )
                        );
                    }

                    /*
                     * hasCharlieFindings is intentionally not treated as
                     * a coverage gap. No open Charlie finding can be the
                     * correct state and is not missing evidence.
                     */
                }
            );

        return new BusinessStateGaps(
            unknowns: $unknowns
                ->values(),

            evidenceGaps: $evidenceGaps
                ->values()
        );
    }

    private function businessGap(
        string $domain,
        string $type,
        string $title,
        string $description
    ): BusinessStateGap {
        return new BusinessStateGap(
            domain: $domain,

            type: $type,

            scope: 'business',

            clientId: null,

            client: null,

            title: $title,

            description: $description
        );
    }

    private function clientGap(
        ClientState $client,
        string $type,
        string $title,
        string $description
    ): BusinessStateGap {
        return new BusinessStateGap(
            domain: 'client',

            type: $type,

            scope: 'client',

            clientId: $client->clientId,

            client: $client->client,

            title: $title,

            description: $description
        );
    }
}
