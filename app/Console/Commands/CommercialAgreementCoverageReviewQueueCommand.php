<?php

namespace App\Console\Commands;

use App\Domains\CommercialTruth\DTO\CommercialAgreementCoverageReviewCandidate;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageReviewQueueService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CommercialAgreementCoverageReviewQueueCommand extends Command
{
    protected $signature =
        'money:contract-review-queue
        {--as-of= : Review the position as of YYYY-MM-DD}
        {--limit=25 : Maximum rows to display; use 0 for all}';

    protected $description =
        'Display the read-only contracted-commercial coverage review queue';

    public function handle(
        CommercialAgreementCoverageReviewQueueService $queue,
        CommercialAgreementCoverageService $coverage
    ): int {
        try {
            $asOf =
                $this->option('as-of')
                    ? CarbonImmutable::parse(
                        (string) $this->option(
                            'as-of'
                        )
                    )
                    : CarbonImmutable::today();
        } catch (
            Throwable
        ) {
            $this->error(
                'Invalid --as-of date.'
            );

            return self::FAILURE;
        }

        $limit =
            (int) $this->option(
                'limit'
            );

        if (
            $limit < 0
        ) {
            $this->error(
                '--limit must be zero or greater.'
            );

            return self::FAILURE;
        }

        $summary =
            $coverage->summary(
                $asOf
            );

        $items =
            $queue->ready(
                $asOf
            );

        $this->newLine();

        $this->info(
            'Contract Coverage Review Queue'
        );

        $this->line(
            'As of: '
            .$asOf->toDateString()
        );

        $this->line(
            sprintf(
                '%d unresolved of %d in scope; %d valid terminal.',
                $summary[
                    'unresolved_count'
                ],
                $summary[
                    'scope_count'
                ],
                $summary[
                    'terminal_count'
                ]
            )
        );

        $this->newLine();

        if (
            $items->isEmpty()
        ) {
            $this->info(
                'No unresolved contract coverage reviews.'
            );

            return self::SUCCESS;
        }

        $shown =
            $limit === 0
                ? $items
                : $items->take(
                    $limit
                );

        foreach (
            $shown as $index => $item
        ) {
            $this->presentCandidate(
                position: $index + 1,

                item: $item
            );
        }

        if (
            $shown->count()
            < $items->count()
        ) {
            $this->line(
                sprintf(
                    'Showing %d of %d unresolved services. Use --limit=0 to show all.',
                    $shown->count(),
                    $items->count()
                )
            );
        }

        return self::SUCCESS;
    }

    private function presentCandidate(
        int $position,
        CommercialAgreementCoverageReviewCandidate $item
    ): void {
        $observedMonthly =
            $item
                ->observedCurrentMonthlyEquivalent
                !== null
                ? '£'.number_format(
                    $item
                        ->observedCurrentMonthlyEquivalent,
                    2
                )
                    : 'UNKNOWN';

        $agreement =
            $item
                ->currentAgreementStatus
                !== null
                ? implode(
                    ' / ',
                    array_filter([
                        $item
                            ->currentAgreementStatus,

                        $item
                            ->currentAgreementCadence,

                        $item
                            ->currentAgreementMonthlyEquivalent
                            !== null
                            ? '£'.number_format(
                                $item
                                    ->currentAgreementMonthlyEquivalent,
                                2
                            )
                                : null,
                    ])
                )
                    : 'NONE';

        $this->line(
            sprintf(
                '#%d  Priority %d  %s — %s',
                $position,
                $item->priority,
                $item->clientName,
                $item->serviceName
            )
        );

        $this->line(
            '  Coverage: '
            .$item->coverageState
        );

        $this->line(
            sprintf(
                '  Observed billing: %s | cadence: %s | freshness: %s | current £/mo: %s',
                $item->observedBillingState,
                $item->observedCadence
                    ?? 'UNKNOWN',
                $item->observedFreshness
                    ?? 'UNKNOWN',
                $observedMonthly
            )
        );

        $this->line(
            sprintf(
                '  Evidence: %d | first seen: %s | last seen: %s',
                $item->observedEvidenceCount,
                $item->firstObservedOn
                    ?? 'NONE',
                $item->lastObservedOn
                    ?? 'NONE'
            )
        );

        $this->line(
            '  Current agreement: '
            .$agreement
        );

        $this->line(
            '  Why now: '
            .$item->priorityReason
        );

        $this->line(
            '  Available decisions: '
            .implode(
                ', ',
                $item->availableDecisions
            )
        );

        $this->line(
            '  ClientService: '
            .$item->clientServiceId
        );

        $this->newLine();
    }
}
