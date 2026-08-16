<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Experience\BusinessExperienceService;
use App\Models\InvestigationCase;
use Illuminate\Console\Command;

class BackfillBusinessExperiences extends Command
{
    protected $signature =
        'business:experiences:backfill
        {--dry-run : Show what would be captured without writing}
        {--type= : Restrict to one investigation type}';

    protected $description =
        'Capture durable Business Experience from genuine historical closed investigations.';

    public function __construct(
        private BusinessExperienceService $experiences
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query =
            InvestigationCase::query()
                ->where(
                    'status',
                    'closed'
                )
                ->whereNotNull(
                    'closed_at'
                )
                ->doesntHave(
                    'experience'
                )
                ->orderBy(
                    'closed_at'
                );

        $type =
            trim(
                (string) $this->option(
                    'type'
                )
            );

        if ($type !== '') {
            $query->where(
                'type',
                $type
            );
        }

        $cases =
            $query->get();

        if ($cases->isEmpty()) {
            $this->components->info(
                'No historical closed investigations require Experience capture.'
            );

            return self::SUCCESS;
        }

        $this->components->info(
            sprintf(
                '%d closed %s found for Experience capture.',
                $cases->count(),
                $cases->count() === 1
                    ? 'investigation'
                    : 'investigations'
            )
        );

        $this->newLine();

        foreach ($cases as $case) {
            $this->line(
                sprintf(
                    '- %s | %s | %d%%',
                    $case->subject_name
                        ?? $case->title,
                    $case->type,
                    (int) $case->confidence
                )
            );

            if ($this->option('dry-run')) {
                continue;
            }

            $this->experiences
                ->capture(
                    $case
                );
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->components->warn(
                'Dry run only. No Business Experience records were created.'
            );

            return self::SUCCESS;
        }

        $this->components->info(
            sprintf(
                '%d Business %s captured.',
                $cases->count(),
                $cases->count() === 1
                    ? 'Experience was'
                    : 'Experiences were'
            )
        );

        return self::SUCCESS;
    }
}
