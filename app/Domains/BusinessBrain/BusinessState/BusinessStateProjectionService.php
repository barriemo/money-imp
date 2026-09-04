<?php

namespace App\Domains\BusinessBrain\BusinessState;

use App\Domains\BusinessBrain\RevenueTruth\CommercialGap;
use Illuminate\Support\Collection;

class BusinessStateProjectionService
{
    public function __construct(
        private BusinessStateService $state
    ) {}

    public function current(): BusinessStateProjection
    {
        return $this->project(
            $this->state
                ->current()
        );
    }

    public function project(
        BusinessState $state
    ): BusinessStateProjection {
        $financial =
            $state->financial;

        $revenue =
            $state->revenue;

        /*
         * Every line must preserve the evidence boundary.
         *
         * £0 of verified evidence must not read as proof that the
         * underlying real-world amount is £0.
         */
        $financialFacts =
            collect([
                sprintf(
                    'Verified cash established from %d of %d account record%s: £%s.',
                    $financial->cash
                        ->verifiedAccountCount,
                    $financial->cash
                        ->accountCount,
                    $financial->cash
                        ->accountCount === 1
                            ? ''
                            : 's',
                    $this->money(
                        $financial->cash
                            ->verifiedCash
                    )
                ),

                sprintf(
                    'Known net position from established evidence: £%s.',
                    $this->money(
                        $financial->cash
                            ->knownNetPosition
                    )
                ),

                sprintf(
                    'Ledger outstanding receivables: £%s.',
                    $this->money(
                        $financial->receivables
                            ->ledgerOutstanding
                    )
                ),

                sprintf(
                    '%s: £%s.',
                    $financial->liabilities
                        ->coverageComplete
                            ? 'Known liability exposure'
                            : 'Known liability exposure captured so far',
                    $this->money(
                        $financial->liabilities
                            ->known
                    )
                ),

                sprintf(
                    'Recorded active credit facilities: %d; verified exposure: £%s.',
                    $financial->credit
                        ->facilityCount,
                    $this->money(
                        $financial->credit
                            ->verifiedExposure
                    )
                ),
            ]);

        if (
            $financial->cash
                ->safeAvailableCash !== null
        ) {
            $financialFacts->push(
                sprintf(
                    'Safe available cash: £%s.',
                    $this->money(
                        $financial->cash
                            ->safeAvailableCash
                    )
                )
            );
        }

        if (
            $financial->receivables
                ->verifiedCollectible !== null
        ) {
            $financialFacts->push(
                sprintf(
                    'Verified collectible receivables: £%s.',
                    $this->money(
                        $financial->receivables
                            ->verifiedCollectible
                    )
                )
            );
        }

        if (
            $financial->receivables
                ->paymentsWaitingAllocation > 0
        ) {
            $financialFacts->push(
                sprintf(
                    'Payments waiting allocation: £%s.',
                    $this->money(
                        $financial->receivables
                            ->paymentsWaitingAllocation
                    )
                )
            );
        }

        /*
         * "Active" here is explicitly the current client-record status.
         * It is not an inferred claim about relationship quality,
         * commercial value or genuine current engagement.
         */
        $commercialFacts =
            collect([
                sprintf(
                    'Client records marked active: %d.',
                    $revenue->clientCount
                ),

                sprintf(
                    'Gross invoiced revenue represented across those client records: £%s.',
                    $this->money(
                        $revenue->grossInvoiced
                    )
                ),

                sprintf(
                    'Accounting records paid revenue of £%s.',
                    $this->money(
                        $revenue->paidAccordingToAccounting
                    )
                ),

                sprintf(
                    'Outstanding invoiced revenue: £%s.',
                    $this->money(
                        $revenue->outstanding
                    )
                ),

                sprintf(
                    'Approved bank-backed payment evidence totals £%s.',
                    $this->money(
                        $revenue->bankVerifiedPaymentValue
                    )
                ),

                sprintf(
                    'Client records with outstanding revenue: %d.',
                    $revenue->clientsWithOutstandingRevenue
                ),

                sprintf(
                    'Client records with weak payment evidence: %d.',
                    $revenue->clientsWithWeakPaymentEvidence
                ),

                sprintf(
                    'Average commercial evidence confidence: %d%%.',
                    $revenue->averageCommercialConfidence
                ),
            ]);

        $clientsWithWorkEvidence =
            max(
                0,
                $revenue->clientCount
                - $revenue->clientsWithoutWorkEvidence
            );

        /*
         * Zero unrecovered work cannot be presented as proof that no
         * unbilled work exists when work-log coverage is incomplete.
         */
        $workFacts =
            collect([
                sprintf(
                    'Work-log evidence is present for %d of %d active client record%s.',
                    $clientsWithWorkEvidence,
                    $revenue->clientCount,
                    $revenue->clientCount === 1
                        ? ''
                        : 's'
                ),

                sprintf(
                    'Unrecovered work value established from recorded work logs: £%s.',
                    $this->money(
                        $revenue->unrecoveredWorkValue
                    )
                ),
            ]);

        $commercialConditions =
            $this->commercialConditions(
                $revenue->gaps,
                $revenue->outstanding,
                $revenue->clientsWithOutstandingRevenue,
                $revenue->clientsWithWeakPaymentEvidence,
                $revenue->paidAccordingToAccounting,
                $revenue->bankVerifiedPaymentValue,
                $revenue->unrecoveredWorkValue
            );

        $unknowns =
            $state->gaps
                ->unknowns
                ->map(
                    fn (BusinessStateGap $gap) => sprintf(
                        '%s — %s',
                        $gap->title,
                        $gap->description
                    )
                )
                ->values();

        /*
         * Detailed client-level gaps remain in BusinessState.
         *
         * The default human projection groups them so an executive
         * state remains readable even when hundreds of records have
         * the same missing evidence.
         */
        $evidenceGaps =
            $this->evidenceGapSummary(
                $state->gaps
                    ->evidenceGaps
            );

        return new BusinessStateProjection(
            financialFacts: $financialFacts
                ->values(),

            commercialFacts: $commercialFacts
                ->values(),

            workFacts: $workFacts
                ->values(),

            commercialConditions: $commercialConditions,

            unknowns: $unknowns,

            evidenceGaps: $evidenceGaps,

            asOf: $state->asOf
        );
    }

    private function commercialConditions(
        Collection $gaps,
        float $outstanding,
        int $clientsWithOutstandingRevenue,
        int $clientsWithWeakPaymentEvidence,
        float $paidAccordingToAccounting,
        float $bankVerifiedPaymentValue,
        float $unrecoveredWorkValue
    ): Collection {
        $conditions =
            collect();

        if (
            $clientsWithOutstandingRevenue > 0
        ) {
            $conditions->push(
                sprintf(
                    'Outstanding invoiced revenue totals £%s across %d client record%s.',
                    $this->money(
                        $outstanding
                    ),
                    $clientsWithOutstandingRevenue,
                    $clientsWithOutstandingRevenue === 1
                        ? ''
                        : 's'
                )
            );

            $largest =
                $gaps
                    ->where(
                        'type',
                        'outstanding_revenue'
                    )
                    ->filter(
                        fn (CommercialGap $gap) => $gap->value !== null
                    )
                    ->sort(
                        function (
                            CommercialGap $left,
                            CommercialGap $right
                        ): int {
                            $valueOrder =
                                ($right->value ?? 0)
                                <=>
                                ($left->value ?? 0);

                            if ($valueOrder !== 0) {
                                return $valueOrder;
                            }

                            return strcmp(
                                $left->client,
                                $right->client
                            );
                        }
                    )
                    ->take(5)
                    ->map(
                        fn (CommercialGap $gap) => sprintf(
                            '%s £%s',
                            $gap->client,
                            $this->money(
                                $gap->value ?? 0
                            )
                        )
                    )
                    ->values();

            if ($largest->isNotEmpty()) {
                $conditions->push(
                    'Largest recorded outstanding balances: '
                    .$largest->implode(
                        '; '
                    )
                    .'.'
                );
            }
        }

        if (
            $clientsWithWeakPaymentEvidence > 0
        ) {
            $conditions->push(
                sprintf(
                    '%d client record%s have incomplete bank-backed evidence for payments accounting marks as paid; accounting-paid revenue is £%s and approved bank-backed payment evidence is £%s.',
                    $clientsWithWeakPaymentEvidence,
                    $clientsWithWeakPaymentEvidence === 1
                        ? ''
                        : 's',
                    $this->money(
                        $paidAccordingToAccounting
                    ),
                    $this->money(
                        $bankVerifiedPaymentValue
                    )
                )
            );
        }

        if ($unrecoveredWorkValue > 0) {
            $unbilledCount =
                $gaps
                    ->where(
                        'type',
                        'unbilled_work'
                    )
                    ->count();

            $conditions->push(
                sprintf(
                    'Recorded unrecovered work totals £%s across %d client record%s.',
                    $this->money(
                        $unrecoveredWorkValue
                    ),
                    $unbilledCount,
                    $unbilledCount === 1
                        ? ''
                        : 's'
                )
            );
        }

        /*
         * Missing work evidence is an evidence-boundary problem and is
         * summarised under Evidence gaps, not repeated as a commercial
         * condition in the executive projection.
         */
        $handledTypes = [
            'outstanding_revenue',
            'weak_payment_evidence',
            'unbilled_work',
            'missing_work_evidence',
        ];

        $gaps
            ->reject(
                fn (CommercialGap $gap) => in_array(
                    $gap->type,
                    $handledTypes,
                    true
                )
            )
            ->groupBy(
                'type'
            )
            ->each(
                function (
                    Collection $group
                ) use ($conditions): void {
                    $first =
                        $group->first();

                    if (! $first instanceof CommercialGap) {
                        return;
                    }

                    $conditions->push(
                        sprintf(
                            '%s: %d client record%s.',
                            $first->title,
                            $group->count(),
                            $group->count() === 1
                                ? ''
                                : 's'
                        )
                    );
                }
            );

        return $conditions
            ->values();
    }

    private function evidenceGapSummary(
        Collection $gaps
    ): Collection {
        $summary =
            collect();

        $gaps
            ->where(
                'scope',
                'business'
            )
            ->each(
                function (
                    BusinessStateGap $gap
                ) use ($summary): void {
                    $summary->push(
                        sprintf(
                            '%s — %s',
                            $gap->title,
                            $gap->description
                        )
                    );
                }
            );

        $gaps
            ->where(
                'scope',
                'client'
            )
            ->groupBy(
                'type'
            )
            ->sortKeys()
            ->each(
                function (
                    Collection $group
                ) use ($summary): void {
                    $first =
                        $group->first();

                    if (! $first instanceof BusinessStateGap) {
                        return;
                    }

                    $summary->push(
                        sprintf(
                            '%s: %d active client record%s.',
                            $first->title,
                            $group->count(),
                            $group->count() === 1
                                ? ''
                                : 's'
                        )
                    );
                }
            );

        return $summary
            ->values();
    }

    private function money(
        float $value
    ): string {
        return number_format(
            $value,
            2
        );
    }
}
