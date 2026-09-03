<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Models\BusinessMemoryEntry;
use App\Models\InvestigationCase;

final class CeoSignalFindingService
{
    public function forEntry(
        BusinessMemoryEntry $entry
    ): ?CeoSignalFinding {
        $routing =
            $entry->metadata[
                'routing'
            ] ?? null;

        if (
            ! is_array($routing)
            || (
                $routing[
                    'status'
                ] ?? null
            ) !== 'routed'
            || (
                $routing[
                    'domain'
                ] ?? null
            ) !== 'client_ledger'
            || (
                $routing[
                    'linked_investigation_case_id'
                ] ?? null
            ) === null
        ) {
            return null;
        }

        $case =
            InvestigationCase::query()
                ->find(
                    $routing[
                        'linked_investigation_case_id'
                    ]
                );

        if (! $case) {
            return null;
        }

        $event =
            $case->events()
                ->where(
                    'type',
                    'payment_evidence_search'
                )
                ->where(
                    'payload->business_memory_entry_id',
                    $entry->id
                )
                ->latest(
                    'occurred_at'
                )
                ->first();

        if (! $event) {
            return $this->waitingFinding(
                $routing
            );
        }

        $payload =
            $event->payload
            ?? [];

        $searchState =
            (string) (
                $payload[
                    'state'
                ]
                ?? 'unknown'
            );

        return match ($searchState) {
            'no_supported_payment_candidate_found' => $this->noSupportedCandidate(
                routing: $routing,
                payload: $payload
            ),

            'supported_payment_candidate_found' => $this->supportedCandidate(
                routing: $routing,
                payload: $payload
            ),

            'weak_unidentified_exact_amount_candidates' => $this->weakAmountEvidence(
                routing: $routing,
                payload: $payload
            ),

            'bank_date_span_incomplete' => $this->incompleteCoverage(
                routing: $routing,
                payload: $payload
            ),

            'bank_evidence_missing' => $this->bankEvidenceMissing(
                routing: $routing,
                payload: $payload
            ),

            default => $this->unknownSearchState(
                routing: $routing,
                payload: $payload,
                searchState: $searchState
            ),
        };
    }

    private function noSupportedCandidate(
        array $routing,
        array $payload
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            $this->outstanding(
                routing: $routing,
                payload: $payload
            );

        $invoiceCount =
            $this->invoiceCount(
                routing: $routing,
                payload: $payload
            );

        $summary = [
            sprintf(
                'Accounting reports £%s outstanding across %d invoice%s.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's'
            ),

            'Money Imp searched the available bank evidence and found no supported receipt candidate linked by client identity or invoice reference.',
        ];

        if (
            (
                $payload[
                    'bank_date_span_covers_invoices'
                ] ?? false
            ) === true
        ) {
            $summary[] =
                'The available bank evidence date span covers the invoice period.';
        }

        $namedAmountCollisions =
            (int) (
                $payload[
                    'named_other_exact_amount_coincidence_count'
                ]
                ?? 0
            );

        if ($namedAmountCollisions > 0) {
            $summary[] =
                sprintf(
                    '%d same-amount bank transaction%s were found, but %s associated with other named payers; amount coincidence alone is not payment identity.',
                    $namedAmountCollisions,
                    $namedAmountCollisions === 1
                        ? ''
                        : 's',
                    $namedAmountCollisions === 1
                        ? 'it was'
                        : 'they were'
                );
        }

        return new CeoSignalFinding(
            state: 'investigation_requires_attention',

            searchState: 'no_supported_payment_candidate_found',

            subjectName: $subject,

            headline: sprintf(
                '%s: £%s remains unresolved',
                $subject,
                number_format(
                    $outstanding,
                    2
                )
            ),

            summary: implode(
                ' ',
                $summary
            ),

            nextStep: 'Check for missing bank sources or an alternate payer identity, then reconcile any supported receipt evidence before deciding whether this balance should be chased.',

            truthBoundary: $this->truthBoundary(
                $payload
            ),

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: (bool) (
                $payload[
                    'bank_date_span_covers_invoices'
                ]
                ?? false
            ),

            supportedCandidateCount: (int) (
                $payload[
                    'supported_candidate_count'
                ]
                ?? 0
            )
        );
    }

    private function supportedCandidate(
        array $routing,
        array $payload
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            $this->outstanding(
                routing: $routing,
                payload: $payload
            );

        $invoiceCount =
            $this->invoiceCount(
                routing: $routing,
                payload: $payload
            );

        $candidateCount =
            (int) (
                $payload[
                    'supported_candidate_count'
                ]
                ?? 0
            );

        return new CeoSignalFinding(
            state: 'candidate_requires_verification',

            searchState: 'supported_payment_candidate_found',

            subjectName: $subject,

            headline: sprintf(
                '%s: possible receipt evidence needs verification',
                $subject
            ),

            summary: sprintf(
                'Accounting reports £%s outstanding across %d invoice%s. Money Imp found %d bank transaction%s with supported evidence linking %s to the client or an invoice reference. %s remain evidence candidates only: no payment allocation or non-payment verdict has been created.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's',
                $candidateCount,
                $candidateCount === 1
                    ? ''
                    : 's',
                $candidateCount === 1
                    ? 'it'
                    : 'them',
                $candidateCount === 1
                    ? 'It'
                    : 'They'
            ),

            nextStep: 'Review the candidate transaction evidence and confirm payer and invoice linkage before allocating any receipt or changing the ledger conclusion.',

            truthBoundary: $this->truthBoundary(
                $payload
            ),

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: (bool) (
                $payload[
                    'bank_date_span_covers_invoices'
                ]
                ?? false
            ),

            supportedCandidateCount: $candidateCount
        );
    }

    private function weakAmountEvidence(
        array $routing,
        array $payload
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            $this->outstanding(
                routing: $routing,
                payload: $payload
            );

        $invoiceCount =
            $this->invoiceCount(
                routing: $routing,
                payload: $payload
            );

        $weakCount =
            (int) (
                $payload[
                    'anonymous_exact_amount_coincidence_count'
                ]
                ?? 0
            );

        return new CeoSignalFinding(
            state: 'weak_evidence_requires_review',

            searchState: 'weak_unidentified_exact_amount_candidates',

            subjectName: $subject,

            headline: sprintf(
                '%s: unidentified same-amount receipts need review',
                $subject
            ),

            summary: sprintf(
                'Accounting reports £%s outstanding across %d invoice%s. Money Imp found %d unidentified incoming transaction%s with amounts matching invoice evidence, but amount coincidence alone cannot identify the payer or prove an invoice was paid.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's',
                $weakCount,
                $weakCount === 1
                    ? ''
                    : 's'
            ),

            nextStep: 'Identify the payer behind the unexplained transaction evidence before considering any receipt allocation or debtor conclusion.',

            truthBoundary: $this->truthBoundary(
                $payload
            ),

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: (bool) (
                $payload[
                    'bank_date_span_covers_invoices'
                ]
                ?? false
            ),

            supportedCandidateCount: (int) (
                $payload[
                    'supported_candidate_count'
                ]
                ?? 0
            )
        );
    }

    private function bankEvidenceMissing(
        array $routing,
        array $payload
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            $this->outstanding(
                routing: $routing,
                payload: $payload
            );

        $invoiceCount =
            $this->invoiceCount(
                routing: $routing,
                payload: $payload
            );

        return new CeoSignalFinding(
            state: 'evidence_missing',

            searchState: 'bank_evidence_missing',

            subjectName: $subject,

            headline: sprintf(
                '%s: bank evidence is missing',
                $subject
            ),

            summary: sprintf(
                'Accounting reports £%s outstanding across %d invoice%s, but Money Imp does not currently have bank transaction evidence to search for the relevant period. The accounting balance therefore cannot be treated as proof that payment was not received.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's'
            ),

            nextStep: 'Obtain or import the relevant bank transaction evidence before making any payment, reconciliation or collection conclusion.',

            truthBoundary: 'No conclusion about payment presence or absence can be drawn while the relevant bank evidence is missing.',

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: false,

            supportedCandidateCount: 0
        );
    }

    private function incompleteCoverage(
        array $routing,
        array $payload
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            $this->outstanding(
                routing: $routing,
                payload: $payload
            );

        $invoiceCount =
            $this->invoiceCount(
                routing: $routing,
                payload: $payload
            );

        return new CeoSignalFinding(
            state: 'evidence_coverage_incomplete',

            searchState: 'bank_date_span_incomplete',

            subjectName: $subject,

            headline: sprintf(
                '%s: bank evidence coverage is incomplete',
                $subject
            ),

            summary: sprintf(
                'Accounting reports £%s outstanding across %d invoice%s, but the available bank evidence does not span the full invoice period. Money Imp therefore cannot treat the absence of a supported receipt candidate as meaningful negative evidence.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's'
            ),

            nextStep: 'Complete the relevant bank evidence coverage before drawing any conclusion about whether payment evidence exists.',

            truthBoundary: $this->truthBoundary(
                $payload
            ),

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: false,

            supportedCandidateCount: (int) (
                $payload[
                    'supported_candidate_count'
                ]
                ?? 0
            )
        );
    }

    private function waitingFinding(
        array $routing
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            (float) (
                $routing[
                    'accounting_outstanding'
                ]
                ?? 0
            );

        $invoiceCount =
            (int) (
                $routing[
                    'invoice_count'
                ]
                ?? 0
            );

        return new CeoSignalFinding(
            state: 'waiting_for_payment_evidence',

            searchState: 'not_yet_searched',

            subjectName: $subject,

            headline: sprintf(
                '%s: payment evidence search still required',
                $subject
            ),

            summary: sprintf(
                'Accounting currently reports £%s outstanding across %d invoice%s. The client-ledger investigation exists, but no payment-evidence search has yet been captured for this CEO signal.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's'
            ),

            nextStep: 'Search the available payment evidence before forming any view about the outstanding balance.',

            truthBoundary: 'The accounting balance is evidence, not proof that payment did or did not occur.',

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: false,

            supportedCandidateCount: 0
        );
    }

    private function unknownSearchState(
        array $routing,
        array $payload,
        string $searchState
    ): CeoSignalFinding {
        $subject =
            $this->subjectName(
                $routing
            );

        $outstanding =
            $this->outstanding(
                routing: $routing,
                payload: $payload
            );

        $invoiceCount =
            $this->invoiceCount(
                routing: $routing,
                payload: $payload
            );

        return new CeoSignalFinding(
            state: 'investigation_evidence_incomplete',

            searchState: $searchState,

            subjectName: $subject,

            headline: sprintf(
                '%s: investigation evidence needs review',
                $subject
            ),

            summary: sprintf(
                'Accounting reports £%s outstanding across %d invoice%s. Money Imp has payment-search evidence, but its current state does not support a stronger CEO conclusion.',
                number_format(
                    $outstanding,
                    2
                ),
                $invoiceCount,
                $invoiceCount === 1
                    ? ''
                    : 's'
            ),

            nextStep: 'Review the underlying investigation evidence before making a ledger or collection decision.',

            truthBoundary: $this->truthBoundary(
                $payload
            ),

            accountingOutstanding: $outstanding,

            invoiceCount: $invoiceCount,

            bankDateSpanCoversInvoices: (bool) (
                $payload[
                    'bank_date_span_covers_invoices'
                ]
                ?? false
            ),

            supportedCandidateCount: (int) (
                $payload[
                    'supported_candidate_count'
                ]
                ?? 0
            )
        );
    }

    private function subjectName(
        array $routing
    ): string {
        return (string) (
            $routing[
                'subject_name'
            ]
            ?? 'Client'
        );
    }

    private function outstanding(
        array $routing,
        array $payload
    ): float {
        return (float) (
            $payload[
                'accounting_outstanding'
            ]
            ?? $routing[
                'accounting_outstanding'
            ]
            ?? 0
        );
    }

    private function invoiceCount(
        array $routing,
        array $payload
    ): int {
        return (int) (
            $payload[
                'invoice_count'
            ]
            ?? $routing[
                'invoice_count'
            ]
            ?? 0
        );
    }

    private function truthBoundary(
        array $payload
    ): string {
        return (string) (
            $payload[
                'truth_boundary'
            ]
            ??
            'Payment evidence may support or weaken a hypothesis, but it does not itself prove that payment did or did not occur.'
        );
    }
}
