<?php

namespace App\Console\Commands;

use App\Models\CapabilityDefinition;
use Illuminate\Console\Command;

class CapabilityHealthCommand extends Command
{
    protected $signature = 'imp:capability-health';

    protected $description = 'Show Money Imp capability health';

    public function handle(): int
    {
        $capabilities = CapabilityDefinition::all();

        foreach ($capabilities as $capability) {
            $health = $this->calculateHealth(
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

    protected function calculateHealth(
        CapabilityDefinition $capability
    ): int {
        $health = match ($capability->status) {
            'ready' => 50,
            'registered' => 25,
            default => 0,
        };

        $health += count($capability->layers) * 10;

        return min(
            $health,
            100
        );
    }
}
