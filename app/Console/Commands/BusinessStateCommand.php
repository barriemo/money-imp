<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\BusinessState\BusinessStatePresenter;
use App\Domains\BusinessBrain\BusinessState\BusinessStateProjectionService;
use Illuminate\Console\Command;

class BusinessStateCommand extends Command
{
    protected $signature =
        'business:state';

    protected $description =
        'Show the current evidence-backed business state';

    public function handle(
        BusinessStateProjectionService $service,
        BusinessStatePresenter $presenter
    ): int {
        $this->line(
            $presenter->present(
                $service->current()
            )
        );

        return self::SUCCESS;
    }
}
