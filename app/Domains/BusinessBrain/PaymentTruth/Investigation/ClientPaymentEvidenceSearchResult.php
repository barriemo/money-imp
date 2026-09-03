<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Investigation;

final readonly class ClientPaymentEvidenceSearchResult
{
    public function __construct(
        public string $clientId,

        public ?string $clientName,

        public string $state,

        public int $invoiceCount,

        public float $accountingPaid,

        public float $accountingOutstanding,

        public float $canonicalCash,

        public ?string $firstInvoiceAt,

        public ?string $lastInvoiceAt,

        public ?string $bankFirstTransactionAt,

        public ?string $bankLastTransactionAt,

        public bool $bankDateSpanCoversInvoices,

        public int $paymentIdentityCount,

        public int $highConfidencePaymentIdentityCount,

        public array $aliases,

        public int $directAliasHitCount,

        public int $paymentIdentityHitCount,

        public int $explicitInvoiceReferenceHitCount,

        public int $exactAmountCoincidenceCount,

        public int $namedOtherExactAmountCoincidenceCount,

        public int $anonymousExactAmountCoincidenceCount,

        public array $supportedCandidates,

        public string $truthBoundary,
    ) {}

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,

            'client_name' => $this->clientName,

            'state' => $this->state,

            'invoice_count' => $this->invoiceCount,

            'accounting_paid' => $this->accountingPaid,

            'accounting_outstanding' => $this->accountingOutstanding,

            'canonical_cash' => $this->canonicalCash,

            'first_invoice_at' => $this->firstInvoiceAt,

            'last_invoice_at' => $this->lastInvoiceAt,

            'bank_first_transaction_at' => $this->bankFirstTransactionAt,

            'bank_last_transaction_at' => $this->bankLastTransactionAt,

            'bank_date_span_covers_invoices' => $this->bankDateSpanCoversInvoices,

            'payment_identity_count' => $this->paymentIdentityCount,

            'high_confidence_payment_identity_count' => $this->highConfidencePaymentIdentityCount,

            'aliases' => $this->aliases,

            'direct_alias_hit_count' => $this->directAliasHitCount,

            'payment_identity_hit_count' => $this->paymentIdentityHitCount,

            'explicit_invoice_reference_hit_count' => $this->explicitInvoiceReferenceHitCount,

            'exact_amount_coincidence_count' => $this->exactAmountCoincidenceCount,

            'named_other_exact_amount_coincidence_count' => $this->namedOtherExactAmountCoincidenceCount,

            'anonymous_exact_amount_coincidence_count' => $this->anonymousExactAmountCoincidenceCount,

            'supported_candidate_count' => count(
                $this->supportedCandidates
            ),

            'supported_candidates' => $this->supportedCandidates,

            'truth_boundary' => $this->truthBoundary,
        ];
    }
}
