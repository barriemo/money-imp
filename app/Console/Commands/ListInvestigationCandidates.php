<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidateService;
use Illuminate\Console\Command;

class ListInvestigationCandidates extends Command
{
    protected $signature =
        'business:investigations:candidates
        {--limit=10 : Maximum number of candidates to show}';

    protected $description =
        'Show unresolved business issues that Money Imp believes deserve investigation.';

    public function __construct(
        private InvestigationCandidateService $candidates
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit =
            max(
                1,
                (int) $this->option(
                    'limit'
                )
            );

        $candidates =
            $this->candidates
                ->current()
                ->take(
                    $limit
                )
                ->values();

        if ($candidates->isEmpty()) {
            $this->components->info(
                'Money Imp has no new investigation candidates at the moment.'
            );

            return self::SUCCESS;
        }

        $this->components->info(
            sprintf(
                '%d investigation %s',
                $candidates->count(),
                $candidates->count() === 1
                    ? 'candidate'
                    : 'candidates'
            )
        );

        $this->newLine();

        foreach ($candidates as $index => $candidate) {
            $this->line(
                sprintf(
                    '<fg=cyan>%d. %s</>',
                    $index + 1,
                    $candidate->subjectName
                )
            );

            $this->line(
                sprintf(
                    '   Priority: %d',
                    $candidate->priority
                )
            );

            $this->line(
                sprintf(
                    '   Confidence: %d%%',
                    $candidate->confidence
                )
            );

            $this->line(
                sprintf(
                    '   Classification: %s',
                    str_replace(
                        '_',
                        ' ',
                        $candidate->classification
                    )
                )
            );

            $this->line(
                sprintf(
                    '   Investigation: %s',
                    $candidate->title
                )
            );

            if ($candidate->reasons !== []) {
                $this->line(
                    '   Why:'
                );

                foreach ($candidate->reasons as $reason) {
                    $this->line(
                        '   - '.$reason
                    );
                }
            }

            if ($candidate->actions !== []) {
                $this->line(
                    '   Suggested actions:'
                );

                foreach ($candidate->actions as $action) {
                    $this->line(
                        '   - '.$action
                    );
                }
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
