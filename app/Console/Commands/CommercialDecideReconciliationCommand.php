<?php

namespace App\Console\Commands;

use App\Domains\Commercial\Decision\CommercialDecisionPresenter;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Commercial\Decision\CommercialDecisionService;
use App\Domains\Commercial\Decision\ServiceReconciliationReadinessPolicy;
use Illuminate\Console\Command;

class CommercialDecideReconciliationCommand extends Command
{
    protected $signature =
        'commercial:decide-reconciliation
        {client_id : Exact client UUID}
        {candidate_fingerprint : Inferred commercial candidate fingerprint}
        {evidence_fingerprint : Exact invoice-item evidence fingerprint}';

    protected $description =
        'Assess whether one exact commercial evidence set should proceed to human service reconciliation';

    public function handle(
        CommercialDecisionService $service,
        CommercialDecisionPresenter $presenter
    ): int {
        $clientId =
            $this->requiredIdentity(
                'client_id',
                'Client id'
            );

        if ($clientId === null) {
            return self::FAILURE;
        }

        $candidateFingerprint =
            $this->requiredIdentity(
                'candidate_fingerprint',
                'Candidate fingerprint'
            );

        if ($candidateFingerprint === null) {
            return self::FAILURE;
        }

        $evidenceFingerprint =
            $this->requiredIdentity(
                'evidence_fingerprint',
                'Evidence fingerprint'
            );

        if ($evidenceFingerprint === null) {
            return self::FAILURE;
        }

        $request =
            new CommercialDecisionRequest(
                key: ServiceReconciliationReadinessPolicy::KEY,

                question: 'Should this exact commercial evidence set proceed to human service reconciliation now?',

                clientId: $clientId,

                candidateFingerprint: $candidateFingerprint,

                evidenceFingerprint: $evidenceFingerprint
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
