<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\PaymentTruth\Historical\HistoricalPaymentVerificationService;
use Illuminate\Console\Command;

class HistoricalPaymentVerificationCommand extends Command
{
    protected $signature = 'business:payments:verify-history';

    protected $description = 'Create historical invoice payment verification candidates from bank evidence';

    public function handle(
        HistoricalPaymentVerificationService $verification
    ): int {
        $stats =
            $verification
                ->generate();

        $this->info(
            'Historical payment verification complete.'
        );

        $this->line(
            'Considered: '.$stats['considered']
        );

        $this->line(
            'Created suggestions: '.$stats['created']
        );

        $this->line(
            sprintf(
                'Suggested value: £%s',
                number_format(
                    $stats['value_suggested'],
                    2
                )
            )
        );

        $this->line(
            'Ambiguous: '.$stats['ambiguous']
        );

        $this->line(
            'No exact match: '.$stats['no_match']
        );

        $this->line(
            'Already allocated: '.$stats['already_allocated']
        );

        return self::SUCCESS;
    }
}
