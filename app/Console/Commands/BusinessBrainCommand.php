<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\Investigation\Candidates\InvestigationCandidate;
use Illuminate\Console\Command;

class BusinessBrainCommand extends Command
{
    protected $signature =
        'business:brain';

    protected $description =
        'Show the current Money Imp Business Brain briefing.';

    public function __construct(
        private BusinessBrainBriefService $brief
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $brief =
            $this->brief
                ->current();

        $this->components->info(
            'Money Imp Business Brain'
        );

        $this->newLine();

        $this->line(
            sprintf(
                'Active investigations: %d',
                $brief->activeInvestigationCount
            )
        );

        $this->line(
            sprintf(
                'Waiting for evidence: %d',
                $brief->waitingInvestigationCount
            )
        );

        $this->line(
            sprintf(
                'New investigation candidates: %d',
                $brief->candidateCount
            )
        );

        $this->line(
            sprintf(
                '   Ready now: %d',
                $brief->readyNowCount
            )
        );

        $this->line(
            sprintf(
                '   Waiting for evidence: %d',
                $brief->waitingForEvidenceCandidateCount
            )
        );

        $this->line(
            sprintf(
                '   Lower priority: %d',
                $brief->lowerPriorityCandidateCount
            )
        );

        $this->line(
            sprintf(
                'Recently solved: %d',
                $brief->recentlyClosedCount
            )
        );

        $this->line(
            sprintf(
                'Business experiences learned: %d',
                $brief->experienceCount
            )
        );

        $this->line(
            sprintf(
                'Average active investigation confidence: %d%%',
                $brief->averageActiveConfidence
            )
        );

        if ($brief->bestNextCandidate) {
            $this->newLine();

            $this->candidate(
                'Best next investigation',
                $brief->bestNextCandidate
            );
        }

        if ($brief->highestConfidenceCandidate) {
            $this->newLine();

            $this->candidate(
                'Highest-confidence candidate',
                $brief->highestConfidenceCandidate
            );
        }

        if ($brief->highestImpactCandidate) {
            $this->newLine();

            $this->candidate(
                'Highest financial-impact candidate',
                $brief->highestImpactCandidate
            );
        }

        if (
            $brief->activeInvestigationCount === 0
            && $brief->candidateCount === 0
        ) {
            $this->newLine();

            $this->components->info(
                'There is currently no investigation work requiring attention.'
            );
        }

        return self::SUCCESS;
    }

    private function candidate(
        string $label,
        InvestigationCandidate $candidate
    ): void {
        $this->line(
            sprintf(
                '<fg=cyan>%s:</> %s',
                $label,
                $candidate->subjectName
            )
        );

        $this->line(
            sprintf(
                '   Priority: %d | Confidence: %d%%',
                $candidate->priority,
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

        $difference =
            $candidate->metadata[
                'ledger_difference'
            ]
            ?? null;

        if ($difference !== null) {
            $this->line(
                sprintf(
                    '   Ledger difference: %s£%s',
                    (float) $difference < 0
                        ? '-'
                        : '',
                    number_format(
                        abs(
                            (float) $difference
                        ),
                        2
                    )
                )
            );
        }

        $this->line(
            '   '.$candidate->title
        );
    }
}
