<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityHealthService;
use App\Models\CapabilityDefinition;
use Illuminate\Console\Command;

class CapabilityHealthCommand extends Command
{
    protected $signature = 'imp:capability-health';

    protected $description = 'Show Money Imp capability health';

    public function __construct(
        protected CapabilityHealthService $health
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $capabilities = CapabilityDefinition::all();

        foreach ($capabilities as $capability) {
            $health = $this->health->calculate(
                $capability
            );

            $this->line(
                '--------------------------------'
            );

            $this->info(
                $capability->name
            );

            $this->line(
                "Owner: {$capability->owner}"
            );

            $this->line(
                "Status: {$capability->status}"
            );

            $this->line(
                'Layers: '.count($capability->layers)
            );

            $this->line(
                "Health: {$health}%"
            );
        }

        return self::SUCCESS;
    }
}
