<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Models\ExecutiveAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:action:complete {id} {outcome} {--financial-result=}')]
#[Description('Complete an executive action and record its outcome')]
class BusinessActionCompleteCommand extends Command
{
    public function handle(
        ExecutiveActionService $actions
    ): int {
        $action =
            ExecutiveAction::query()
                ->findOrFail(
                    $this->argument('id')
                );

        $financialResult =
            $this->option(
                'financial-result'
            );

        $action =
            $actions->complete(
                action: $action,

                outcome: $this->argument(
                    'outcome'
                ),

                financialResult: $financialResult !== null
                    ? (float) $financialResult
                    : null
            );

        $this->info(
            sprintf(
                'Completed: %s - %s',
                $action->client ?? 'Business',
                $action->title
            )
        );

        $this->line(
            'Outcome: '.$action->outcome
        );

        if ($action->financial_result !== null) {
            $this->line(
                'Financial result: £'.number_format(
                    (float) $action->financial_result,
                    2
                )
            );
        }

        return self::SUCCESS;
    }
}
