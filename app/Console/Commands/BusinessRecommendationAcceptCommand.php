<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Decisions\Outcomes\BusinessDecisionOutcomeService;
use App\Models\BusinessDecisionOutcome;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:recommendation:accept {id}')]
#[Description('Accept a tracked business recommendation')]
class BusinessRecommendationAcceptCommand extends Command
{
    public function handle(
        BusinessDecisionOutcomeService $outcomes
    ): int {
        $recommendation =
            BusinessDecisionOutcome::query()
                ->findOrFail(
                    $this->argument('id')
                );

        $recommendation =
            $outcomes->accept(
                $recommendation
            );

        $this->info(
            sprintf(
                'Recommendation accepted: %s - %s',
                $recommendation->client ?? 'Business',
                $recommendation->action
            )
        );

        return self::SUCCESS;
    }
}
