<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangePresenter;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeReportService;
use Illuminate\Console\Command;

class BusinessChangesCommand extends Command
{
    protected $signature =
        'business:changes';

    protected $description =
        'Show evidence-backed business changes and deterministic attention';

    public function handle(
        BusinessStateChangeReportService $service,
        BusinessStateChangePresenter $presenter
    ): int {
        $this->line(
            $presenter->present(
                $service->current()
            )
        );

        return self::SUCCESS;
    }
}
