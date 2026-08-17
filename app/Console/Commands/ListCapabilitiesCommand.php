<?php

namespace App\Console\Commands;

use App\Models\CapabilityDefinition;
use Illuminate\Console\Command;

class ListCapabilitiesCommand extends Command
{
    protected $signature = 'imp:capabilities';

    protected $description = 'List registered Money Imp capabilities';

    public function handle(): int
    {
        $capabilities = CapabilityDefinition::orderBy(
            'domain'
        )->orderBy(
            'name'
        )->get();

        if ($capabilities->isEmpty()) {
            $this->info(
                'No capabilities registered.'
            );

            return self::SUCCESS;
        }

        foreach ($capabilities as $capability) {
            $this->line(
                '--------------------------------'
            );

            $this->info(
                $capability->name
            );

            $this->line(
                "Domain: {$capability->domain}"
            );

            $this->line(
                "Area: {$capability->area}"
            );

            $this->line(
                "Owner: {$capability->owner}"
            );

            $this->line(
                "Status: {$capability->status}"
            );

            $this->line(
                "Purpose: {$capability->purpose}"
            );

            $this->line(
                'Layers: '.implode(
                    ', ',
                    $capability->layers
                )
            );
        }

        return self::SUCCESS;
    }
}
