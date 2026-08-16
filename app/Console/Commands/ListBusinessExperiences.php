<?php

namespace App\Console\Commands;

use App\Models\BusinessExperience;
use Illuminate\Console\Command;

class ListBusinessExperiences extends Command
{
    protected $signature =
        'business:experiences
        {--subject= : Filter by subject name}
        {--type= : Filter by experience type}
        {--limit=20 : Maximum number of experiences to show}';

    protected $description =
        'List durable business experiences learned from completed investigations.';

    public function handle(): int
    {
        $query =
            BusinessExperience::query()
                ->latest(
                    'experienced_at'
                );

        $subject =
            trim(
                (string) $this->option(
                    'subject'
                )
            );

        if ($subject !== '') {
            $query->where(
                'subject_name',
                'like',
                '%'.$subject.'%'
            );
        }

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

        $limit =
            max(
                1,
                (int) $this->option(
                    'limit'
                )
            );

        $experiences =
            $query
                ->limit(
                    $limit
                )
                ->get();

        if ($experiences->isEmpty()) {
            $this->components->info(
                'Money Imp has not captured any matching business experiences yet.'
            );

            return self::SUCCESS;
        }

        $this->components->info(
            sprintf(
                'Business Experience — %d %s',
                $experiences->count(),
                $experiences->count() === 1
                    ? 'experience'
                    : 'experiences'
            )
        );

        $this->newLine();

        foreach (
            $experiences->values() as $index => $experience
        ) {
            $this->line(
                sprintf(
                    '<fg=cyan>%d. %s</>',
                    $index + 1,
                    $experience->subject_name
                        ?? $experience->title
                )
            );

            $this->line(
                sprintf(
                    '   Type: %s',
                    $experience->type
                )
            );

            $this->line(
                sprintf(
                    '   Confidence: %d%%',
                    $experience->confidence
                )
            );

            $this->line(
                sprintf(
                    '   Importance: %d',
                    $experience->importance
                )
            );

            if ($experience->hypothesis) {
                $this->line(
                    '   Hypothesis: '
                    .$experience->hypothesis
                );
            }

            if ($experience->outcome) {
                $this->line(
                    '   Outcome: '
                    .$experience->outcome
                );
            }

            $this->line(
                sprintf(
                    '   Experienced: %s',
                    $experience->experienced_at
                        ?->format(
                            'Y-m-d H:i'
                        )
                        ?? 'unknown'
                )
            );

            $this->line(
                '   Source case: '
                .$experience->source_investigation_case_id
            );

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
