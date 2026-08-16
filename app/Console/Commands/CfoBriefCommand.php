<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Cfo\Briefing\CfoBrief;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\BusinessBrain\Cfo\Presenters\CfoBriefPresenter;
use Illuminate\Console\Command;

class CfoBriefCommand extends Command
{
    protected $signature = 'cfo:brief';

    protected $description = 'Show the current Money Imp CFO executive brief';

    public function handle(
        CfoBriefService $service,
        CfoBriefPresenter $presenter
    ): int {
        $brief =
            $service->current();

        if (! $brief instanceof CfoBrief) {
            return self::FAILURE;
        }

        $this->line(
            $presenter->present(
                $brief
            )
        );

        return self::SUCCESS;
    }
}
