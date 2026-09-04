<?php

namespace App\Console\Commands;

use App\Domains\Cfo\Decision\CfoDecisionPresenter;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\CfoDecisionService;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use Illuminate\Console\Command;

class CfoDecideSpendCommand extends Command
{
    protected $signature =
        'cfo:decide-spend
        {amount : Proposed discretionary spend in GBP}
        {--recurring : Treat the spend as a recurring commitment}';

    protected $description =
        'Assess a discretionary spend using authoritative CFO decision truth';

    public function handle(
        CfoDecisionService $service,
        CfoDecisionPresenter $presenter
    ): int {
        $rawAmount =
            $this->argument(
                'amount'
            );

        if (
            ! is_scalar($rawAmount)
            || ! is_numeric(
                (string) $rawAmount
            )
        ) {
            $this->error(
                'Spend amount must be a positive numeric GBP value.'
            );

            return self::FAILURE;
        }

        $amount =
            (float) $rawAmount;

        if (
            ! is_finite($amount)
            || $amount <= 0
        ) {
            $this->error(
                'Spend amount must be a positive numeric GBP value.'
            );

            return self::FAILURE;
        }

        $request =
            new CfoDecisionRequest(
                key: DiscretionarySpendDecisionPolicy::KEY,

                question: 'Can the business safely make this discretionary spend?',

                parameters: [
                    'amount' => $amount,

                    'currency' => 'GBP',

                    'recurring' => (bool) $this->option(
                        'recurring'
                    ),
                ]
            );

        $decision =
            $service
                ->decide(
                    $request
                );

        $this->line(
            $presenter->present(
                $decision
            )
        );

        return self::SUCCESS;
    }
}
