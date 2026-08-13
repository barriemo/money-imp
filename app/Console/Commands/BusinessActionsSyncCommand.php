<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:actions:sync {--limit=100}')]
#[Description('Persist the current highest-priority executive actions')]
class BusinessActionsSyncCommand extends Command
{
    public function handle(
        ExecutiveActionService $actions
    ): int {
        $limit =
            max(
                1,
                (int) $this->option('limit')
            );

        $synced =
            $actions->syncCurrent(
                $limit
            );

        $this->info(
            sprintf(
                'Synced %d executive action%s.',
                $synced->count(),
                $synced->count() === 1
                    ? ''
                    : 's'
            )
        );

        return self::SUCCESS;
    }
}
