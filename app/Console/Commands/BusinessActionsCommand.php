<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:actions {--status=pending} {--limit=20}')]
#[Description('List executive actions by status')]
class BusinessActionsCommand extends Command
{
    public function handle(
        ExecutiveActionService $actions
    ): int {
        $limit =
            max(
                1,
                (int) $this->option('limit')
            );

        $status =
            (string) $this->option(
                'status'
            );

        $items =
            $actions
                ->byStatus(
                    $status
                )
                ->take(
                    $limit
                );

        if ($items->isEmpty()) {
            $this->info(
                sprintf(
                    'No executive actions in %s state.',
                    $status
                )
            );

            return self::SUCCESS;
        }

        foreach ($items as $index => $action) {
            $this->line(
                sprintf(
                    '%d. [%d] [%s] %s',
                    $index + 1,
                    $action->score,
                    strtoupper(
                        $action->status
                    ),
                    $action->client
                        ? $action->client.' - '.$action->title
                        : $action->title
                )
            );

            $this->line(
                '   '.$action->recommended_action
            );

            if ($action->outcome) {
                $this->line(
                    '   Current note: '.$action->outcome
                );
            }

            if ($action->estimated_financial_impact !== null) {
                $this->line(
                    '   Value: £'.number_format(
                        (float) $action
                            ->estimated_financial_impact,
                        2
                    )
                );
            }

            if ($action->estimated_effort_minutes !== null) {
                $this->line(
                    '   Effort: '.$action
                        ->estimated_effort_minutes.' minutes'
                );
            }

            $this->line(
                '   ID: '.$action->id
            );

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
