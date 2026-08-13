<?php

namespace App\Console\Commands;

use App\Models\BusinessDecisionOutcome;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:recommendations')]
#[Description('List tracked business recommendations')]
class BusinessRecommendationsCommand extends Command
{
    public function handle(): int
    {
        $recommendations =
            BusinessDecisionOutcome::query()
                ->orderByDesc('priority')
                ->orderByDesc('created_at')
                ->get();

        if ($recommendations->isEmpty()) {
            $this->info(
                'No recommendations have been recorded.'
            );

            return self::SUCCESS;
        }

        foreach ($recommendations as $index => $recommendation) {
            $this->line(
                sprintf(
                    '%d. [%s] %s',
                    $index + 1,
                    strtoupper($recommendation->status),
                    $recommendation->client ?? 'Business'
                )
            );

            $this->line(
                '   ID: '.$recommendation->id
            );

            $this->line(
                '   '.$recommendation->action
            );

            if ($recommendation->reason) {
                $this->line(
                    '   '.$recommendation->reason
                );
            }

            if ($recommendation->value !== null) {
                $this->line(
                    '   Value: £'.number_format(
                        $recommendation->value,
                        2
                    )
                );
            }

            $this->line(
                '   Priority: '.$recommendation->priority
            );

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
