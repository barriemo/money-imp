<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Decisions\Outcomes\BusinessDecisionOutcomeService;
use App\Models\BusinessDecisionOutcome;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:recommendation:complete {id} {result} {--financial-result=}')]
#[Description('Complete a tracked business recommendation')]
class BusinessRecommendationCompleteCommand extends Command
{
    public function handle(
        BusinessDecisionOutcomeService $outcomes
    ): int {
        $recommendation =
            BusinessDecisionOutcome::query()
                ->findOrFail(
                    $this->argument('id')
                );

        $financialResult =
            $this->option('financial-result');

        $recommendation =
            $outcomes->complete(
                outcome: $recommendation,

                result: $this->argument('result'),

                financialResult: $financialResult !== null
                    ? (float) $financialResult
                    : null
            );

        $this->info(
            sprintf(
                'Recommendation completed: %s - %s',
                $recommendation->client ?? 'Business',
                $recommendation->outcome
            )
        );

        if ($recommendation->financial_result !== null) {
            $this->line(
                'Financial result: £'.number_format(
                    $recommendation->financial_result,
                    2
                )
            );
        }

        return self::SUCCESS;
    }
}
