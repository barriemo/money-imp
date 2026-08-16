<?php

namespace App\Console\Commands;

use App\Domains\FinancialTruth\Verification\Presenters\VerificationQueuePresenter;
use App\Domains\FinancialTruth\Verification\Services\VerificationQueueService;
use Illuminate\Console\Command;

class FinancialVerificationCommand extends Command
{
    protected $signature =
        'money:verification';

    protected $description =
        'Show financial evidence that should be verified next.';

    public function handle(
        VerificationQueueService $verification,
        VerificationQueuePresenter $presenter
    ): int {
        $this->line(
            $presenter->present(
                $verification->current()
            )
        );

        return self::SUCCESS;
    }
}
