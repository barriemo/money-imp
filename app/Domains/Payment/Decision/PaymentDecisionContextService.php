<?php

namespace App\Domains\Payment\Decision;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchService;
use App\Models\Client;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class PaymentDecisionContextService
{
    public function __construct(
        private ClientPaymentEvidenceSearchService $paymentEvidence,
    ) {}

    public function forDecision(
        PaymentDecisionRequest $request,
        ?CarbonImmutable $observedAt = null
    ): PaymentDecisionContext {
        $observedAt ??=
            CarbonImmutable::now();

        $client =
            Client::query()
                ->find(
                    $request->clientId
                );

        if ($client === null) {
            throw new InvalidArgumentException(
                'Payment decision subject client does not exist.'
            );
        }

        /*
         * Payment OS context is assembled only from the exact Client
         * record and the established read-only client payment-evidence
         * search contract.
         *
         * This layer does not interpret the search state into guidance.
         * It does not allocate or approve payments, run reconciliation
         * matchers, rank clients, trigger collections or persist an
         * authoritative decision outcome.
         */
        $evidence =
            $this->paymentEvidence
                ->search(
                    (string) $client->getKey()
                );

        return new PaymentDecisionContext(
            request: $request,
            paymentEvidence: $evidence,
            observedAt: $observedAt
        );
    }
}
