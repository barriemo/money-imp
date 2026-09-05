<?php

namespace App\Console\Commands;

use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Commercial\Decision\ServiceReconciliationReadinessPolicy;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use App\Domains\Executive\Decision\ExecutiveDecisionPresenter;
use App\Domains\Executive\Decision\ExecutiveDecisionRequest;
use App\Domains\Executive\Decision\ExecutiveDecisionService;
use App\Domains\Executive\Decision\ManagementResponseReadinessPolicy;
use Illuminate\Console\Command;

class ExecutiveDecideManagementResponseCommand extends Command
{
    protected $signature =
        'executive:decide-management-response
        {--cfo-amount= : Proposed discretionary spend in GBP}
        {--cfo-recurring : Treat the CFO spend as a recurring commitment}
        {--commercial-client-id= : Exact commercial client UUID}
        {--commercial-candidate-fingerprint= : Commercial candidate fingerprint}
        {--commercial-evidence-fingerprint= : Exact commercial evidence fingerprint}
        {--delivery-client-id= : Exact delivery client UUID}';

    protected $description =
        'Assess one explicit cross-domain specialist decision set for bounded human management review';

    public function handle(
        ExecutiveDecisionService $service,
        ExecutiveDecisionPresenter $presenter
    ): int {
        $cfoRequest = null;
        $commercialRequest = null;
        $deliveryRequest = null;

        $cfoSelected =
            $this->option('cfo-amount') !== null
            || (bool) $this->option('cfo-recurring');

        if ($cfoSelected) {
            $rawAmount =
                $this->option(
                    'cfo-amount'
                );

            if (
                ! is_scalar($rawAmount)
                || ! is_numeric((string) $rawAmount)
            ) {
                $this->error(
                    'CFO amount must be a positive numeric GBP value when the CFO domain is selected.'
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
                    'CFO amount must be a positive numeric GBP value when the CFO domain is selected.'
                );

                return self::FAILURE;
            }

            $cfoRequest =
                new CfoDecisionRequest(
                    key: DiscretionarySpendDecisionPolicy::KEY,
                    question: 'Can the business safely make this discretionary spend?',
                    parameters: [
                        'amount' => $amount,
                        'currency' => 'GBP',
                        'recurring' => (bool) $this->option(
                            'cfo-recurring'
                        ),
                    ]
                );
        }

        $commercialSelected =
            $this->option('commercial-client-id') !== null
            || $this->option('commercial-candidate-fingerprint') !== null
            || $this->option('commercial-evidence-fingerprint') !== null;

        if ($commercialSelected) {
            $clientId =
                $this->requiredIdentityOption(
                    'commercial-client-id',
                    'Commercial client id'
                );

            $candidateFingerprint =
                $this->requiredIdentityOption(
                    'commercial-candidate-fingerprint',
                    'Commercial candidate fingerprint'
                );

            $evidenceFingerprint =
                $this->requiredIdentityOption(
                    'commercial-evidence-fingerprint',
                    'Commercial evidence fingerprint'
                );

            if (
                $clientId === null
                || $candidateFingerprint === null
                || $evidenceFingerprint === null
            ) {
                return self::FAILURE;
            }

            $commercialRequest =
                new CommercialDecisionRequest(
                    key: ServiceReconciliationReadinessPolicy::KEY,
                    question: 'Should this exact commercial evidence set proceed to human service reconciliation now?',
                    clientId: $clientId,
                    candidateFingerprint: $candidateFingerprint,
                    evidenceFingerprint: $evidenceFingerprint
                );
        }

        if ($this->option('delivery-client-id') !== null) {
            $clientId =
                $this->requiredIdentityOption(
                    'delivery-client-id',
                    'Delivery client id'
                );

            if ($clientId === null) {
                return self::FAILURE;
            }

            $deliveryRequest =
                new DeliveryDecisionRequest(
                    key: DeliveryEvidenceReviewReadinessPolicy::KEY,
                    question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',
                    clientId: $clientId
                );
        }

        $specialistCount =
            collect([
                $cfoRequest,
                $commercialRequest,
                $deliveryRequest,
            ])
                ->filter()
                ->count();

        if ($specialistCount < 2) {
            $this->error(
                'Executive management response requires at least two explicitly selected specialist decision domains.'
            );

            return self::FAILURE;
        }

        $request =
            new ExecutiveDecisionRequest(
                key: ManagementResponseReadinessPolicy::KEY,
                question: 'Does this explicit cross-domain specialist decision set support a bounded human management response now?',
                cfoRequest: $cfoRequest,
                commercialRequest: $commercialRequest,
                deliveryRequest: $deliveryRequest
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

    private function requiredIdentityOption(
        string $option,
        string $label
    ): ?string {
        $value =
            $this->option(
                $option
            );

        if (! is_scalar($value)) {
            $this->error(
                $label.' must be a non-empty string.'
            );

            return null;
        }

        $value =
            trim(
                (string) $value
            );

        if ($value === '') {
            $this->error(
                $label.' must be a non-empty string.'
            );

            return null;
        }

        return $value;
    }
}
