<?php

namespace App\Console\Commands;

use App\Domains\OperatingSystem\Presenters\OperatingSystemPresenter;
use Illuminate\Console\Command;

class OperatingSystemCommand extends Command
{
    protected $signature = 'os';

    protected $description = 'Show the Money Imp Business Operating System';

    public function handle(
        OperatingSystemPresenter $presenter
    ): int {
        $this->line(
            $presenter->present()
        );

        return self::SUCCESS;
    }
}
