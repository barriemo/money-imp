<?php

namespace App\Console\Commands;

use App\Domains\Payment\Decision\PaymentDecisionPresenter;
use App\Domains\Payment\Decision\PaymentDecisionRequest;
use App\Domains\Payment\Decision\PaymentDecisionService;
use App\Domains\Payment\Decision\PaymentEvidenceConclusionReadinessPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PaymentDecideEvidenceConclusionCommand extends Command
{
    protected $signature =
        'payment:decide-evidence-conclusion
        {client_id : Exact client UUID}';

    protected $description =
        'Assess whether payment evidence for one exact client supports a bounded human payment-evidence conclusion';

    public function handle(
        PaymentDecisionService $service,
        PaymentDecisionPresenter $presenter
    ): int {
        $clientId =
            $this->requiredUuid(
                'client_id',
                'Client id'
            );

        if ($clientId === null) {
            return self::FAILURE;
        }

        $request =
            new PaymentDecisionRequest(
                key: PaymentEvidenceConclusionReadinessPolicy::KEY,

                question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

                clientId: $clientId
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

    private function requiredUuid(
        string $argument,
        string $label
    ): ?string {
        $value =
            $this->argument(
                $argument
            );

        if (! is_scalar($value)) {
            $this->error(
                $label
                .' must be a valid UUID.'
            );

            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if (
            $value === ''
            || ! Str::isUuid(
                $value
            )
        ) {
            $this->error(
                $label
                .' must be a valid UUID.'
            );

            return null;
        }

        return $value;
    }
}
