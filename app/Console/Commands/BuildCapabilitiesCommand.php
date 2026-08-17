<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use App\Models\CapabilityDefinition;
use Illuminate\Console\Command;

class BuildCapabilitiesCommand extends Command
{
    protected $signature = 'imp:build-capabilities';

    protected $description = 'Build registered Money Imp capabilities';

    public function __construct(
        protected CapabilityGenerator $generator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $capabilities = CapabilityDefinition::where(
            'status',
            'registered'
        )->get();

        if ($capabilities->isEmpty()) {
            $this->info(
                'No registered capabilities to build.'
            );

            return self::SUCCESS;
        }

        foreach ($capabilities as $capability) {
            $this->info(
                "Building {$capability->name}"
            );

            $this->generator->generate(
                $capability
            );

            $capability->update([
                'status' => 'ready',
            ]);
        }

        $this->info(
            'Capabilities built.'
        );

        return self::SUCCESS;
    }
}
