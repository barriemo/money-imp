<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Learning\ActionOutcomeProfileService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:learning')]
#[Description('Display learned executive action outcomes')]
class BusinessLearningCommand extends Command
{
    public function handle(
        ActionOutcomeProfileService $profiles
    ): int {
        $items =
            $profiles->byType();

        if ($items->isEmpty()) {
            $this->info(
                'CFO Imp does not yet have enough completed executive actions to report learning.'
            );

            return self::SUCCESS;
        }

        foreach ($items as $profile) {
            $this->line(
                strtoupper(
                    str_replace(
                        '_',
                        ' ',
                        $profile->type
                    )
                )
            );

            $this->line(
                'Completed: '.$profile->completedCount
            );

            $this->line(
                'Financial successes: '.$profile->financialSuccessCount
            );

            $this->line(
                'Success rate: '.$profile->financialSuccessRate.'%'
            );

            $this->line(
                'Financial result: £'.number_format(
                    $profile->totalFinancialResult,
                    2
                )
            );

            $this->line(
                'Average result: £'.number_format(
                    $profile->averageFinancialResult,
                    2
                )
            );

            if ($profile->averageCompletionHours !== null) {
                $this->line(
                    'Average completion: '.number_format(
                        $profile->averageCompletionHours,
                        2
                    ).' hours'
                );
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
