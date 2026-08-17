<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Capabilities\Services\CapabilityDefinitionRegistry;
use App\Domains\BusinessBrain\Capabilities\Services\CapabilityInstaller;
use Illuminate\Console\Command;

class InstallCapabilitiesCommand extends Command
{
    protected $signature = 'imp:install-capabilities';

    protected $description = 'Install Money Imp capabilities';

    public function __construct(
        protected CapabilityInstaller $installer,
        protected CapabilityDefinitionRegistry $registry
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->installer->install(
            $this->registry->definitions()
        );

        $this->info(
            'Capabilities installed.'
        );

        return self::SUCCESS;
    }
}
