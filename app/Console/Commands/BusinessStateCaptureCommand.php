<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateBaselineCaptureService;
use Illuminate\Console\Command;

class BusinessStateCaptureCommand extends Command
{
    protected $signature =
        'business:state:capture';

    protected $description =
        'Capture the current evidence-backed business state as a temporal baseline';

    public function handle(
        BusinessStateBaselineCaptureService $service
    ): int {
        $baseline =
            $service->capture();

        $this->line(
            sprintf(
                'Captured business-state baseline at %s with %d metrics.',
                $baseline
                    ->asOf
                    ->toIso8601String(),
                $baseline
                    ->metrics
                    ->count()
            )
        );

        return self::SUCCESS;
    }
}
