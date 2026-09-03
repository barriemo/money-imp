<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchService;
use App\Models\BusinessMemoryEntry;
use App\Models\InvestigationCase;
use App\Models\InvestigationEvent;

final class CeoSignalReassessmentService
{
    public function __construct(
        private readonly ClientPaymentEvidenceSearchService $paymentEvidence,

        private readonly InvestigationCaseService $cases,
    ) {}

    public function reassess(
        BusinessMemoryEntry $entry
    ): CeoSignalReassessmentResult {
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
                    'subject_id'
                ] ?? null
            ) === null
            || (
                $routing[
                    'linked_investigation_case_id'
                ] ?? null
            ) === null
        ) {
            return $this->notApplicable(
                $entry
            );
        }

        $case =
            InvestigationCase::query()
                ->find(
                    $routing[
                        'linked_investigation_case_id'
                    ]
                );

        if (
            ! $case
            || $case->status === 'closed'
        ) {
            return $this->notApplicable(
                $entry
            );
        }

        $previous =
            $this->latestPaymentEvidenceEvent(
                case: $case,

                entry: $entry
            );

        /*
         * Reassessment is only evolution of an existing evidence
         * search. Initial capture remains the responsibility of
         * CeoSignalRoutingService.
         */
        if (! $previous) {
            return new CeoSignalReassessmentResult(
                entryId: $entry->id,

                status: 'initial_search_missing',

                changed: false,

                previousState: null,

                currentState: null,

                eventId: null
            );
        }

        $result =
            $this->paymentEvidence
                ->search(
                    (string) $routing[
                        'subject_id'
                    ]
                );

        $currentPayload =
            $result->toArray();

        $previousState =
            isset(
                $previous->payload[
                    'state'
                ]
            )
                ? (string) $previous->payload[
                    'state'
                ]
                : null;

        if (
            $this->sameEvidence(
                previousPayload: $previous->payload
                    ?? [],

                currentPayload: $currentPayload
            )
        ) {
            return new CeoSignalReassessmentResult(
                entryId: $entry->id,

                status: 'unchanged',

                changed: false,

                previousState: $previousState,

                currentState: $result->state,

                eventId: $previous->id
            );
        }

        $event =
            $this->cases
                ->event(
                    case: $case,

                    type: 'payment_evidence_reassessment',

                    description: sprintf(
                        'Payment evidence changed for %s after reassessment. No payment allocation, client remap or verdict was created.',
                        $routing[
                            'subject_name'
                        ] ?? 'Client'
                    ),

                    payload: array_merge(
                        [
                            'business_memory_entry_id' => $entry->id,

                            'source' => 'client_payment_evidence_reassessment',

                            'previous_event_id' => $previous->id,

                            'previous_state' => $previousState,

                            'truth_status' => 'unverified',
                        ],
                        $currentPayload
                    )
                );

        return new CeoSignalReassessmentResult(
            entryId: $entry->id,

            status: 'changed',

            changed: true,

            previousState: $previousState,

            currentState: $result->state,

            eventId: $event->id
        );
    }

    private function latestPaymentEvidenceEvent(
        InvestigationCase $case,
        BusinessMemoryEntry $entry
    ): ?InvestigationEvent {
        return $case
            ->events()
            ->whereIn(
                'type',
                [
                    'payment_evidence_search',
                    'payment_evidence_reassessment',
                ]
            )
            ->where(
                'payload->business_memory_entry_id',
                $entry->id
            )
            ->orderByDesc(
                'occurred_at'
            )
            ->orderByDesc(
                'created_at'
            )
            ->first();
    }

    private function sameEvidence(
        array $previousPayload,
        array $currentPayload
    ): bool {
        $previousPayload =
            $this->normalisePaymentEvidenceSchema(
                $previousPayload
            );

        $currentPayload =
            $this->normalisePaymentEvidenceSchema(
                $currentPayload
            );

        $previousEvidence =
            array_intersect_key(
                $previousPayload,

                array_flip(
                    array_keys(
                        $currentPayload
                    )
                )
            );

        return $this->fingerprint(
            $previousEvidence
        ) === $this->fingerprint(
            $currentPayload
        );
    }

    private function normalisePaymentEvidenceSchema(
        array $payload
    ): array {
        /*
         * Stage 4A adds reconciliation-aware projection fields.
         *
         * Historic search events predate these fields. Treat their
         * absence as the old no-approved-allocation semantics so a
         * code deployment alone does not manufacture an evidence
         * reassessment.
         *
         * As soon as approved payment evidence exists, the non-zero
         * fields differ and the normal reassessment path records the
         * material change.
         */
        $payload[
            'confirmed_allocated_payment'
        ] ??= 0.0;

        $payload[
            'allocation_uncovered_amount'
        ] ??= (float) (
            $payload[
                'accounting_outstanding'
            ]
            ?? 0
        );

        $payload[
            'approved_payment_count'
        ] ??= 0;

        $payload[
            'source_outstanding_disagreement_count'
        ] ??= 0;

        return $payload;
    }

    private function fingerprint(
        array $payload
    ): string {
        return hash(
            'sha256',

            json_encode(
                $this->canonicalise(
                    $payload
                ),
                JSON_THROW_ON_ERROR
            )
        );
    }

    private function canonicalise(
        mixed $value
    ): mixed {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item) => $this->canonicalise(
                    $item
                ),
                $value
            );
        }

        ksort(
            $value
        );

        foreach (
            $value as $key => $item
        ) {
            $value[
                $key
            ] =
                $this->canonicalise(
                    $item
                );
        }

        return $value;
    }

    private function notApplicable(
        BusinessMemoryEntry $entry
    ): CeoSignalReassessmentResult {
        return new CeoSignalReassessmentResult(
            entryId: $entry->id,

            status: 'not_applicable',

            changed: false,

            previousState: null,

            currentState: null,

            eventId: null
        );
    }
}
