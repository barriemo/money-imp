<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityActionExecutor;
use App\Models\CapabilityAction;
use Illuminate\Console\Command;

class GenerateCapabilityActionsCommand extends Command
{
    protected $signature = 'imp:generate-actions';

    protected $description = 'Generate executive actions from capability actions';

    public function handle(
        CapabilityActionExecutor $executor
    ): int {
        $actions = CapabilityAction::all();

        foreach ($actions as $action) {
            $result = $executor->execute($action);

            $this->info(
                $result['created']
                    ? "Created action: {$action->name}"
                    : "Existing action: {$action->name}"
            );
        }

        return self::SUCCESS;
    }
}
