<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Generators\CapabilityGenerator;
use Illuminate\Console\Command;

class ImpCapabilityCommand extends Command
{
    protected $signature = 'imp:capability
        {name}
        {--domain=BusinessBrain}';

    protected $description = 'Generate a Money Imp capability scaffold';

    public function __construct(
        protected CapabilityGenerator $generator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $domain = $this->option('domain');

        $this->info(
            "Generating capability: {$name}"
        );

        $this->info(
            "Domain: {$domain}"
        );

        $this->generator->generate(
            $name,
            $domain
        );

        $this->info(
            'Capability generated'
        );

        return self::SUCCESS;
    }
}
