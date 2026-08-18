<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Briefs\Services\BusinessBriefService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('business:brief')]
#[Description('Display the current business executive brief')]
class BusinessBriefCommand extends Command
{
    public function handle(
        BusinessBriefService $briefService
    ): int {
        $brief =
            $briefService->current();

        $this->line('');

        $this->info(
            $brief->business.' Business Brief'
        );

        $this->line('');

        $this->comment(
            'Executive priorities:'
        );

        foreach (
            $brief->actions as $action
        ) {
            $this->line('');

            $this->line(
                '['.$action['priority'].'] '
                .$action['title']
            );

            $this->line(
                $action['description']
            );

            $this->line(
                '→ '.$action['recommended_action']
            );
        }

        $this->line('');

        return self::SUCCESS;
    }
}
