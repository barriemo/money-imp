<?php

namespace App\Console\Commands;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use Illuminate\Console\Command;

class RegisterCapabilityActionCommand extends Command
{
    protected $signature = 'imp:register-action
        {capability}
        {action}
        {--priority=50}';

    protected $description = 'Register an action against a capability';

    public function handle(): int
    {
        $capability = CapabilityDefinition::where(
            'name',
            $this->argument('capability')
        )->first();

        if (! $capability) {
            $this->error(
                'Capability not found'
            );

            return self::FAILURE;
        }

        CapabilityAction::create([
            'capability_definition_id' => $capability->id,
            'name' => $this->argument('action'),
            'priority' => $this->option('priority'),
        ]);

        $this->info(
            "Registered action: {$this->argument('action')}"
        );

        return self::SUCCESS;
    }
}
