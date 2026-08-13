<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Models\ExecutiveAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:action:start {id}')]
#[Description('Start an executive action')]
class BusinessActionStartCommand extends Command
{
    public function handle(
        ExecutiveActionService $actions
    ): int {
        $action =
            ExecutiveAction::query()
                ->findOrFail(
                    $this->argument('id')
                );

        $action =
            $actions->start(
                $action
            );

        $this->info(
            sprintf(
                'Started: %s - %s',
                $action->client ?? 'Business',
                $action->title
            )
        );

        if ($action->estimated_financial_impact !== null) {
            $this->line(
                'Potential financial impact: £'.number_format(
                    (float) $action->estimated_financial_impact,
                    2
                )
            );
        }

        return self::SUCCESS;
    }
}
