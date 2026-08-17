<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityRegistry;
use Illuminate\Console\Command;

class RegisterCapabilityCommand extends Command
{
    protected $signature = 'imp:register-capability
        {name}';

    protected $description = 'Register a Money Imp capability definition';

    public function __construct(
        protected CapabilityRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $capability = $this->registry->register([
            'name' => $name,
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Turn happy clients into introductions',
            'layers' => [
                'model',
                'migration',
                'factory',
                'service',
                'presenter',
                'test',
            ],
        ]);

        $this->info(
            "Registered capability: {$capability->name}"
        );

        return self::SUCCESS;
    }
}
