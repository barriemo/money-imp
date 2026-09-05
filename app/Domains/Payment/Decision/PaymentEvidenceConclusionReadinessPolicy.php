<?php

namespace App\Domains\Payment\Decision;

use InvalidArgumentException;

final class PaymentEvidenceConclusionReadinessPolicy
{
    public const KEY =
        'payment-evidence-conclusion';

    public function supports(
        PaymentDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        PaymentDecisionContext $context
    ): PaymentDecision {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Payment evidence conclusion readiness policy does not support decision request %s.',
                    $context->request->key
                )
            );
        }

        /*
         * Payment OS V1 intentionally has no policy parameters.
         *
         * Any future decision semantics must be added explicitly
         * rather than entering this policy through arbitrary inputs.
         */
        if ($context->request->parameters !== []) {
            throw new InvalidArgumentException(
                'Payment evidence conclusion readiness policy does not accept parameters.'
            );
        }

        return match ($context->paymentEvidence->state) {
            'supported_payment_candidate_found' => $this->recommendSupportedCandidateConclusion(
                $context
            ),

            'no_supported_payment_candidate_found' => $this->recommendBoundedNegativeConclusion(
                $context
            ),

            'weak_unidentified_exact_amount_candidates' => $this->recommendConditionalWeakCandidateConclusion(
                $context
            ),

            'bank_evidence_missing' => $this->deferForMissingBankEvidence(
                $context
            ),

            'bank_date_span_incomplete' => $this->deferForIncompleteBankDateSpan(
                $context
            ),

            'no_invoice_evidence' => $this->deferForMissingInvoiceEvidence(
                $context
            ),

            default => throw new InvalidArgumentException(
                sprintf(
                    'Payment evidence conclusion readiness policy does not recognise payment evidence state %s.',
                    $context->paymentEvidence->state
                )
            ),
        };
    }

    private function recommendSupportedCandidateConclusion(
        PaymentDecisionContext $context
    ): PaymentDecision {
        return new PaymentDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'Available evidence supports at least one payment candidate for this exact client for bounded human review.',

            rationale: 'The read-only client payment-evidence search found one or more supported candidates. This establishes only that the available evidence supports candidate receipt evidence for human review. It does not allocate or approve a payment and does not by itself prove that payment occurred.',

            evidence: collect([
                $this->stateSupport(
                    context: $context,

                    description: sprintf(
                        'The payment-evidence search recorded %d supported payment candidate(s) for this exact client.',
                        count(
                            $context
                                ->paymentEvidence
                                ->supportedCandidates
                        )
                    )
                ),

                $this->paymentContextEvidence(
                    $context
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->observedAt
        );
    }

    private function recommendBoundedNegativeConclusion(
        PaymentDecisionContext $context
    ): PaymentDecision {
        return new PaymentDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'Available evidence does not support a payment candidate for this exact client within the searched evidence.',

            rationale: 'The payment-evidence search has sufficient invoice and bank date-span evidence for the bounded conclusion that no supported receipt candidate was found. This does not establish that no payment occurred. The upstream truth boundary remains authoritative: amount coincidence alone is not payment identity and apparent date-span coverage does not prove every source or payer identity is complete.',

            evidence: collect([
                $this->stateSupport(
                    context: $context,

                    description: 'The payment-evidence search found no supported payment candidate for this exact client within the available searched evidence.'
                ),

                $this->paymentContextEvidence(
                    $context
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->observedAt
        );
    }

    private function recommendConditionalWeakCandidateConclusion(
        PaymentDecisionContext $context
    ): PaymentDecision {
        return new PaymentDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: PaymentDecision::CONDITIONAL,

            recommendation: 'Use only the bounded conclusion that weak unidentified exact-amount payment candidates exist while payer identity remains unresolved.',

            rationale: 'The payment-evidence search found one or more anonymous exact-amount coincidences but no supported payer identity. Amount coincidence alone is not payment identity, so the evidence supports only a conditional human conclusion with that uncertainty preserved.',

            evidence: collect([
                $this->stateSupport(
                    context: $context,

                    description: sprintf(
                        'The payment-evidence search recorded %d anonymous exact-amount candidate(s) without supported payer identity for this exact client.',
                        $context
                            ->paymentEvidence
                            ->anonymousExactAmountCoincidenceCount
                    )
                ),

                $this->paymentContextEvidence(
                    $context
                ),
            ]),

            constraints: collect([
                new PaymentDecisionConstraint(
                    code: 'payer_identity_unresolved',

                    description: 'Exact-amount coincidence exists, but the available evidence does not establish that any weak unidentified candidate belongs to this exact client.',

                    type: PaymentDecisionConstraint::CONDITION,

                    source: 'payment.evidence_search.weak_candidates',

                    confidence: 100,

                    metadata: [
                        'client_id' => $context
                            ->paymentEvidence
                            ->clientId,

                        'anonymous_exact_amount_candidate_count' => $context
                            ->paymentEvidence
                            ->anonymousExactAmountCoincidenceCount,
                    ]
                ),
            ]),

            confidence: 100,

            missingTruth: collect([
                'Whether any weak unidentified exact-amount candidate is a payment from this exact client is not established.',
            ]),

            asOf: $context->observedAt
        );
    }

    private function deferForMissingBankEvidence(
        PaymentDecisionContext $context
    ): PaymentDecision {
        return $this->defer(
            context: $context,

            code: 'bank_evidence_missing',

            source: 'payment.evidence_search.bank',

            description: 'Bank evidence required to support a bounded client payment-evidence conclusion is not recorded.',

            missingTruth: 'Required bank evidence for this exact client payment-evidence conclusion is not established.'
        );
    }

    private function deferForIncompleteBankDateSpan(
        PaymentDecisionContext $context
    ): PaymentDecision {
        return $this->defer(
            context: $context,

            code: 'bank_date_span_incomplete',

            source: 'payment.evidence_search.bank_date_span',

            description: 'Available bank evidence does not span the recorded invoice evidence period for this exact client.',

            missingTruth: 'Bank evidence covering the recorded invoice evidence period for this exact client is incomplete.'
        );
    }

    private function deferForMissingInvoiceEvidence(
        PaymentDecisionContext $context
    ): PaymentDecision {
        return $this->defer(
            context: $context,

            code: 'invoice_evidence_missing',

            source: 'payment.evidence_search.invoices',

            description: 'No invoice evidence is recorded for this exact client, so Payment OS V1 cannot establish a bounded client payment-evidence conclusion.',

            missingTruth: 'Invoice evidence required to anchor this exact client payment-evidence conclusion is not established.'
        );
    }

    private function defer(
        PaymentDecisionContext $context,
        string $code,
        string $source,
        string $description,
        string $missingTruth,
    ): PaymentDecision {
        return new PaymentDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: PaymentDecision::DEFERRED,

            recommendation: null,

            rationale: 'Required Payment OS V1 evidence is incomplete. Payment OS therefore fails closed rather than converting missing payment evidence into a supported or negative payment conclusion.',

            evidence: collect([
                $this->paymentContextEvidence(
                    $context
                ),
            ]),

            constraints: collect([
                new PaymentDecisionConstraint(
                    code: $code,

                    description: $description,

                    type: PaymentDecisionConstraint::BLOCKING,

                    source: $source,

                    confidence: 100,

                    metadata: [
                        'client_id' => $context
                            ->paymentEvidence
                            ->clientId,

                        'payment_evidence_state' => $context
                            ->paymentEvidence
                            ->state,
                    ]
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                $missingTruth,
            ]),

            asOf: $context->observedAt
        );
    }

    private function stateSupport(
        PaymentDecisionContext $context,
        string $description,
    ): PaymentDecisionEvidence {
        return new PaymentDecisionEvidence(
            source: 'payment.evidence_search.state',

            description: $description,

            position: PaymentDecisionEvidence::SUPPORTS,

            confidence: 100,

            metadata: [
                'client_id' => $context
                    ->paymentEvidence
                    ->clientId,

                'state' => $context
                    ->paymentEvidence
                    ->state,

                'supported_candidate_count' => count(
                    $context
                        ->paymentEvidence
                        ->supportedCandidates
                ),

                'anonymous_exact_amount_candidate_count' => $context
                    ->paymentEvidence
                    ->anonymousExactAmountCoincidenceCount,
            ]
        );
    }

    private function paymentContextEvidence(
        PaymentDecisionContext $context
    ): PaymentDecisionEvidence {
        return new PaymentDecisionEvidence(
            source: 'payment.evidence_search.context',

            description: 'Payment OS evaluated the established read-only payment-evidence search result attributable to this exact client.',

            position: PaymentDecisionEvidence::CONTEXT,

            confidence: 100,

            metadata: [
                'client_id' => $context
                    ->paymentEvidence
                    ->clientId,

                'client_name' => $context
                    ->paymentEvidence
                    ->clientName,

                'state' => $context
                    ->paymentEvidence
                    ->state,

                'invoice_count' => $context
                    ->paymentEvidence
                    ->invoiceCount,

                'bank_date_span_covers_invoices' => $context
                    ->paymentEvidence
                    ->bankDateSpanCoversInvoices,

                'payment_identity_count' => $context
                    ->paymentEvidence
                    ->paymentIdentityCount,

                'high_confidence_payment_identity_count' => $context
                    ->paymentEvidence
                    ->highConfidencePaymentIdentityCount,

                'supported_candidate_count' => count(
                    $context
                        ->paymentEvidence
                        ->supportedCandidates
                ),

                'anonymous_exact_amount_candidate_count' => $context
                    ->paymentEvidence
                    ->anonymousExactAmountCoincidenceCount,

                'truth_boundary' => $context
                    ->paymentEvidence
                    ->truthBoundary,

                'observed_at' => $context
                    ->observedAt
                    ->toIso8601String(),
            ]
        );
    }
}
