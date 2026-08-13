<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\WorkLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('money:truth-status')]
#[Description('Display current business truth status')]
class MoneyTruthStatusCommand extends Command
{
    public function handle(): int
    {
        $this->info(
            'BUSINESS TRUTH STATUS'
        );

        $this->newLine();

        $this->line(
            'Clients: '.Client::count()
        );

        $this->line(
            'Work logs: '.WorkLog::count()
        );

        $this->line(
            'Unrecovered commercial value: £'.
            number_format(
                (float) WorkLog::query()
                    ->whereNull(
                        'accounting_invoice_id'
                    )
                    ->sum(
                        'commercial_value'
                    )
            )
        );

        return self::SUCCESS;
    }
}
