<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:actions {--limit=20}')]
#[Description('List current pending executive actions')]
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

        $items =
            $actions
                ->pending()
                ->take(
                    $limit
                );

        if ($items->isEmpty()) {
            $this->info(
                'No pending executive actions.'
            );

            return self::SUCCESS;
        }

        foreach (
            $items as $index => $action
        ) {
            $this->line(
                sprintf(
                    '%d. [%d] %s',
                    $index + 1,
                    $action->score,
                    $action->client
                        ? $action->client.' - '.$action->title
                        : $action->title
                )
            );

            $this->line(
                '   '.$action->recommended_action
            );

            if (
                $action->estimated_financial_impact !== null
            ) {
                $this->line(
                    '   Value: £'.number_format(
                        (float) $action
                            ->estimated_financial_impact,
                        2
                    )
                );
            }

            if (
                $action->estimated_effort_minutes !== null
            ) {
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
