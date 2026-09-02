<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Models\AccountingInvoiceItem;
use App\Models\CompositeCommercialReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompositeCommercialReviewService
{
    private const DECISIONS = [
        'bundled_service',
        'requires_allocation',
        'deferred',
    ];

    private const TERMINAL_DECISIONS = [
        'bundled_service',
        'requires_allocation',
    ];

    public function __construct(
        private readonly ClientServiceCandidateAssessmentService $assessments,
        private readonly CompositeCommercialEvidenceFingerprint $evidenceFingerprint,
    ) {}

    public function bundledService(
        string $clientId,
        string $candidateFingerprint,
        string $invoiceItemId,
        int $reviewedBy,
        ?string $reason = null,
        ?CarbonImmutable $asOf = null
    ): CompositeCommercialReview {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            invoiceItemId: $invoiceItemId,
            decision: 'bundled_service',
            reviewedBy: $reviewedBy,
            reason: $reason,
            asOf: $asOf
        );
    }

    public function requiresAllocation(
        string $clientId,
        string $candidateFingerprint,
        string $invoiceItemId,
        int $reviewedBy,
        ?string $reason = null,
        ?CarbonImmutable $asOf = null
    ): CompositeCommercialReview {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            invoiceItemId: $invoiceItemId,
            decision: 'requires_allocation',
            reviewedBy: $reviewedBy,
            reason: $reason,
            asOf: $asOf
        );
    }

    public function defer(
        string $clientId,
        string $candidateFingerprint,
        string $invoiceItemId,
        int $reviewedBy,
        ?string $reason = null,
        ?CarbonImmutable $asOf = null
    ): CompositeCommercialReview {
        return $this->review(
            clientId: $clientId,
            candidateFingerprint: $candidateFingerprint,
            invoiceItemId: $invoiceItemId,
            decision: 'deferred',
            reviewedBy: $reviewedBy,
            reason: $reason,
            asOf: $asOf
        );
    }

    private function review(
        string $clientId,
        string $candidateFingerprint,
        string $invoiceItemId,
        string $decision,
        int $reviewedBy,
        ?string $reason,
        ?CarbonImmutable $asOf
    ): CompositeCommercialReview {
        if (
            ! in_array(
                $decision,
                self::DECISIONS,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'decision' => 'Unsupported composite commercial review decision.',
            ]);
        }

        $asOf ??=
            CarbonImmutable::today();

        return DB::transaction(
            function () use (
                $clientId,
                $candidateFingerprint,
                $invoiceItemId,
                $decision,
                $reviewedBy,
                $reason,
                $asOf
            ): CompositeCommercialReview {
                $assessment =
                    $this->resolveAssessment(
                        clientId: $clientId,
                        candidateFingerprint: $candidateFingerprint,
                        invoiceItemId: $invoiceItemId,
                        asOf: $asOf
                    );

                $item =
                    AccountingInvoiceItem::query()
                        ->with('invoice')
                        ->lockForUpdate()
                        ->findOrFail(
                            $invoiceItemId
                        );

                if (
                    $item->invoice === null
                    || $item->invoice->client_id
                        !== $clientId
                ) {
                    throw ValidationException::withMessages([
                        'candidate' => 'The reviewed invoice evidence does not belong to this client.',
                    ]);
                }

                if (
                    $item->client_service_id
                    !== null
                ) {
                    throw ValidationException::withMessages([
                        'candidate' => 'This invoice item is already attributed to canonical service truth.',
                    ]);
                }

                /*
                 * Re-resolve after locking the source evidence.
                 */
                $assessment =
                    $this->resolveAssessment(
                        clientId: $clientId,
                        candidateFingerprint: $candidateFingerprint,
                        invoiceItemId: $invoiceItemId,
                        asOf: $asOf
                    );

                $candidate =
                    $assessment->candidate;

                /*
                 * Stage-one composite candidates are source-item
                 * atomic by design. Do not allow a structural review
                 * to act on an inferred multi-item evidence group.
                 */
                if (
                    $candidate->evidenceCount !== 1
                    || $candidate->invoiceItemIds !== [
                        $invoiceItemId,
                    ]
                ) {
                    throw ValidationException::withMessages([
                        'candidate' => 'Composite commercial review requires one exact atomic invoice item.',
                    ]);
                }

                $evidenceFingerprint =
                    $this->evidenceFingerprint
                        ->forInvoiceItem(
                            $item
                        );

                $existingTerminal =
                    CompositeCommercialReview::query()
                        ->where(
                            'accounting_invoice_item_id',
                            $invoiceItemId
                        )
                        ->where(
                            'evidence_fingerprint',
                            $evidenceFingerprint
                        )
                        ->where(
                            'terminal_marker',
                            'terminal'
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($existingTerminal) {
                    throw ValidationException::withMessages([
                        'candidate' => 'This exact composite evidence state already has a terminal structural review decision.',
                    ]);
                }

                return CompositeCommercialReview::create([
                    'accounting_invoice_item_id' => $invoiceItemId,

                    'client_id' => $clientId,

                    'candidate_fingerprint' => $candidate->fingerprint,

                    'evidence_fingerprint' => $evidenceFingerprint,

                    'decision' => $decision,

                    'terminal_marker' => in_array(
                        $decision,
                        self::TERMINAL_DECISIONS,
                        true
                    )
                            ? 'terminal'
                            : null,

                    'reviewed_by' => $reviewedBy,

                    'reviewed_at' => now(),

                    'reason' => $this->cleanReason(
                        $reason
                    ),

                    'candidate_snapshot' => [
                        'as_of_date' => $assessment->asOfDate,

                        'client_id' => $candidate->clientId,

                        'client_name' => $candidate->clientName,

                        'invoice_item_ids' => $candidate->invoiceItemIds,

                        /*
                         * Freeze the exact source state seen by the
                         * reviewer so later changes are observable
                         * rather than silently inheriting this
                         * decision.
                         */
                        'source_evidence' => $this->evidenceFingerprint
                            ->snapshot(
                                $item
                            ),

                        'evidence_fingerprint' => $evidenceFingerprint,

                        'service_type' => $candidate->serviceType,

                        'service_hint' => $candidate->serviceHint,

                        'candidate_fingerprint' => $candidate->fingerprint,

                        'commercial_treatment' => $candidate->commercialTreatment,

                        /*
                     * Activity signals only.
                     *
                     * These are not monetary allocation
                     * instructions.
                     */
                        'detected_activity_families' => $candidate->commercialComponents,

                        'signed_observed_net' => $candidate->signedObservedNet,

                        'classification_confidence' => $candidate->classificationConfidence,

                        'freshness' => $assessment->freshness,

                        'promotion_readiness' => $assessment->promotionReadiness,

                        'assessment_reasons' => $assessment->reasons,
                    ],
                ]);
            }
        );
    }

    private function resolveAssessment(
        string $clientId,
        string $candidateFingerprint,
        string $invoiceItemId,
        CarbonImmutable $asOf
    ): ClientServiceCandidateAssessment {
        $assessment =
            $this->assessments
                ->all(
                    $asOf
                )
                ->first(
                    fn (
                        ClientServiceCandidateAssessment $row
                    ) => $row
                        ->candidate
                        ->clientId
                            === $clientId
                        && $row
                            ->candidate
                            ->fingerprint
                            === $candidateFingerprint
                        && $row
                            ->candidate
                            ->invoiceItemIds
                            === [
                                $invoiceItemId,
                            ]
                );

        if ($assessment === null) {
            throw ValidationException::withMessages([
                'candidate' => 'The atomic composite commercial evidence no longer exists in current evidence.',
            ]);
        }

        if (
            ! $assessment
                ->candidate
                ->isCompositeCandidate()
        ) {
            throw ValidationException::withMessages([
                'candidate' => 'This evidence is not a composite commercial review candidate.',
            ]);
        }

        if (
            $assessment
                ->promotionReadiness
            !== 'needs_commercial_review'
        ) {
            throw ValidationException::withMessages([
                'candidate' => 'This composite evidence is not currently awaiting commercial review.',
            ]);
        }

        return $assessment;
    }

    private function cleanReason(
        ?string $reason
    ): ?string {
        if ($reason === null) {
            return null;
        }

        $reason =
            trim(
                $reason
            );

        return $reason !== ''
            ? $reason
            : null;
    }
}
