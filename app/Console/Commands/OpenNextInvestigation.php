<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Investigation\Opening\InvestigationCandidateOpener;
use Illuminate\Console\Command;

class OpenNextInvestigation extends Command
{
    protected $signature =
        'business:investigations:open-next';

    protected $description =
        'Open the highest-ranked investigation that Money Imp considers ready now.';

    public function __construct(
        private InvestigationCandidateOpener $opener
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $case =
            $this->opener
                ->next();

        if (! $case) {
            $this->components->info(
                'Money Imp has no investigation candidates ready to open.'
            );

            return self::SUCCESS;
        }

        $this->components->info(
            'Investigation opened.'
        );

        $this->newLine();

        $this->line(
            sprintf(
                'Subject: %s',
                $case->subject_name
                    ?? $case->subject_id
            )
        );

        $this->line(
            sprintf(
                'Case: %s',
                $case->title
            )
        );

        $this->line(
            sprintf(
                'Status: %s',
                strtoupper(
                    $case->status
                )
            )
        );

        $this->line(
            sprintf(
                'Case ID: %s',
                $case->id
            )
        );

        $this->newLine();

        $this->line(
            'Next: ask Money Imp why the ledger does not reconcile or provide a hypothesis.'
        );

        return self::SUCCESS;
    }
}
