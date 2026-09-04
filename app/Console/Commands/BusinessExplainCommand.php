<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationPresenter;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReportService;
use Illuminate\Console\Command;

class BusinessExplainCommand extends Command
{
    protected $signature =
        'business:explain';

    protected $description =
        'Show evidence-backed explanations for business-state changes';

    public function handle(
        BusinessStateExplanationReportService $service,
        BusinessStateExplanationPresenter $presenter
    ): int {
        $this->line(
            $presenter->present(
                $service->current()
            )
        );

        return self::SUCCESS;
    }
}
