<?php

namespace App\Console\Commands;

use App\Models\AccountBalanceSnapshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('money:bank-balance:verify {id}')]
#[Description('Verify a bank balance snapshot for Financial Truth')]
class BankBalanceVerifyCommand extends Command
{
    public function handle(): int
    {
        $snapshot =
            AccountBalanceSnapshot::query()
                ->findOrFail(
                    $this->argument('id')
                );

        $snapshot->update([
            'verified' => true,
        ]);

        $this->info(
            sprintf(
                'Verified bank balance snapshot %s at £%s.',
                $snapshot->id,
                number_format(
                    (float) $snapshot->balance,
                    2
                )
            )
        );

        $this->line(
            'Balance at: '.$snapshot->balance_at
        );

        $this->line(
            'Source: '.$snapshot->source
        );

        $this->line(
            'Confidence: '.$snapshot->confidence.'%'
        );

        return self::SUCCESS;
    }
}
