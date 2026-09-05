<?php

namespace App\Console\Commands;

use App\Domains\Delivery\Decision\DeliveryDecisionPresenter;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecisionService;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use Illuminate\Console\Command;

class DeliveryDecideEvidenceReviewCommand extends Command
{
    protected $signature =
        'delivery:decide-evidence-review
        {client_id : Exact client UUID}';

    protected $description =
        'Assess whether one exact client delivery evidence set should proceed to human delivery review';

    public function handle(
        DeliveryDecisionService $service,
        DeliveryDecisionPresenter $presenter
    ): int {
        $clientId =
            $this->requiredIdentity(
                'client_id',
                'Client id'
            );

        if ($clientId === null) {
            return self::FAILURE;
        }

        $request =
            new DeliveryDecisionRequest(
                key: DeliveryEvidenceReviewReadinessPolicy::KEY,

                question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',

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

    private function requiredIdentity(
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
                .' must be a non-empty string.'
            );

            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            $this->error(
                $label
                .' must be a non-empty string.'
            );

            return null;
        }

        return $value;
    }
}
