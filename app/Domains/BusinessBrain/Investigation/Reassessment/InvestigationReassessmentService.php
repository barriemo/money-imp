<?php

namespace App\Domains\BusinessBrain\Investigation\Reassessment;

use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Claims\HypothesisClaimAssessmentService;
use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\Investigation\HypothesisVerificationService;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\BankSourceEvidenceCollector;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientLedgerEvidenceCollector;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\PaymentHypothesisClaimBuilder;
use App\Models\InvestigationCase;

class InvestigationReassessmentService
{
    public function __construct(
        private HypothesisVerificationService $verification,

        private PaymentHypothesisClaimBuilder $claimBuilder,

        private HypothesisClaimAssessmentService $claimAssessment,

        private ClientLedgerEvidenceCollector $ledgerEvidence,

        private BankSourceEvidenceCollector $bankSourceEvidence,

        private InvestigationCaseService $cases
    ) {}

    public function reassess(
        InvestigationCase $case,
        ?EvidenceTrigger $trigger = null
    ): InvestigationCase {
        if (! $case->current_hypothesis) {
            return $case;
        }

        $eventsBefore =
            $case->events()
                ->count();

        $eventMetadata =
            $trigger !== null
                ? [
                    'correlation_id' => $trigger->correlationId,
                ]
                : [];

        $hypothesis =
            new Hypothesis(
                statement: $case->current_hypothesis,

                subjectType: $case->subject_type
                    ?? 'client',

                subjectId: $case->subject_id
                    ?? '',

                subjectName: $case->subject_name,

                assertedBy: 'user'
            );

        $result =
            $this->verification
                ->verify(
                    $hypothesis,
                    [
                        $this->ledgerEvidence,
                        $this->bankSourceEvidence,
                    ]
                );

        $claims =
            $this->claimAssessment
                ->assess(
                    $this->claimBuilder
                        ->build(
                            $hypothesis
                        ),
                    $result->evidence
                );

        $this->cases
            ->assessmentEvent(
                case: $case,

                hypothesis: $hypothesis->statement,

                status: $result->status,

                confidence: $result->confidence,

                payload: [
                    'missing_evidence' => $result->missingEvidence,
                ],

                eventMetadata: $eventMetadata
            );

        foreach ($claims->claims as $claim) {
            $this->cases
                ->claimAssessmentEvent(
                    case: $case,

                    key: $claim->key,

                    statement: $claim->statement,

                    status: $claim->status,

                    confidence: $claim->confidence,

                    evidence: $claim->evidence,

                    eventMetadata: $eventMetadata
                );
        }

        $reasoningChanged =
            $case->events()
                ->count()
            > $eventsBefore;

        if (
            $trigger !== null
            && $reasoningChanged
        ) {
            $this->cases
                ->event(
                    case: $case,

                    type: 'evidence_changed',

                    description: $trigger->description(),

                    payload: [
                        'correlation_id' => $trigger->correlationId,
                        'domain' => $trigger->domain,
                        'type' => $trigger->type,
                        'metadata' => $trigger->metadata,
                    ]
                );
        }

        if ($result->status === 'verified') {
            return $this->cases
                ->close(
                    case: $case,

                    verdict: $result->recommendation,

                    confidence: $result->confidence,

                    reason: sprintf(
                        'Investigation verified: %s',
                        $hypothesis->statement
                    ),

                    eventMetadata: $eventMetadata
                );
        }

        $case->forceFill([
            'confidence' => $result->confidence,
            'status' => 'waiting',
            'verdict' => $result->recommendation,
        ])->save();

        return $case->refresh();
    }
}
