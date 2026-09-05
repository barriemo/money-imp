<?php

namespace App\Console\Commands;

use App\Domains\Billing\Decision\BillingDecisionPresenter;
use App\Domains\Billing\Decision\BillingDecisionRequest;
use App\Domains\Billing\Decision\BillingDecisionService;
use App\Domains\Billing\Decision\BillingEvidenceConclusionReadinessPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BillingDecideEvidenceReadinessCommand extends Command
{
    protected $signature =
        'billing:decide-evidence-readiness
        {client_service_id : Exact client service UUID}';

    protected $description =
        'Assess whether canonical billing evidence for one exact client service supports a bounded human billing-evidence conclusion';

    public function handle(
        BillingDecisionService $service,
        BillingDecisionPresenter $presenter
    ): int {
        $clientServiceId =
            $this->requiredUuid(
                'client_service_id',
                'Client service id'
            );

        if ($clientServiceId === null) {
            return self::FAILURE;
        }

        $request =
            new BillingDecisionRequest(
                key: BillingEvidenceConclusionReadinessPolicy::KEY,

                question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

                clientServiceId: $clientServiceId
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
