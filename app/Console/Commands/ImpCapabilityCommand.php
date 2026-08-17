<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Console\Command;

class ImpCapabilityCommand extends Command
{
    protected $signature = 'imp:capability
        {name}';

    protected $description = 'Generate a Money Imp capability scaffold';

    public function __construct(
        protected CapabilityGenerator $generator,
        protected CapabilityRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $capability = $this->registry->find(
            $name
        );

        if (! $capability) {
            $this->error(
                "Capability definition not found: {$name}"
            );

            return self::FAILURE;
        }

        $this->info(
            "Generating capability: {$capability->name}"
        );

        $this->info(
            "Domain: {$capability->domain}"
        );

        $this->info(
            "Area: {$capability->area}"
        );

        $this->generator->generate(
            $capability
        );

        $this->info(
            'Capability generated'
        );

        return self::SUCCESS;
    }
}
