<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Models\ExecutiveAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:action:wait {id} {reason}')]
#[Description('Mark an executive action as waiting on an external dependency')]
class BusinessActionWaitCommand extends Command
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
            $actions->wait(
                action: $action,

                reason: $this->argument(
                    'reason'
                )
            );

        $this->info(
            sprintf(
                'Waiting: %s - %s',
                $action->client ?? 'Business',
                $action->title
            )
        );

        $this->line(
            'Reason: '.$action->outcome
        );

        return self::SUCCESS;
    }
}
