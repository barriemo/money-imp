<?php

namespace App\Domains\Project\Decision;

use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ProjectReviewReadinessPolicy
{
    public const KEY =
        'project-review';

    public function supports(
        ProjectDecisionRequest $request
    ): bool {
        return $request->key === self::KEY;
    }

    public function decide(
        ProjectDecisionContext $context
    ): ProjectDecision {
        if (! $this->supports($context->request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Project review readiness policy does not support decision request %s.',
                    $context->request->key
                )
            );
        }

        /*
         * Project OS V1 intentionally has no policy parameters.
         *
         * Future decision semantics must be introduced explicitly
         * rather than being silently accepted by this policy.
         */
        if ($context->request->parameters !== []) {
            throw new InvalidArgumentException(
                'Project review readiness policy does not accept parameters.'
            );
        }

        $support =
            $this->recordedReviewSignals(
                $context
            );

        /*
         * Absence of a V1 review signal is not evidence that the
         * project is healthy, complete, on track or free of risk.
         *
         * Project OS therefore fails closed rather than manufacturing
         * negative guidance from an incomplete evidence boundary.
         */
        if ($support->isEmpty()) {
            return $this->deferWithoutAffirmativeReviewSignal(
                $context
            );
        }

        $conditions =
            $this->unresolvedEvidenceConditions(
                $context
            );

        if ($conditions->isNotEmpty()) {
            return $this->recommendConditionalHumanReview(
                context: $context,

                support: $support,

                conditions: $conditions
            );
        }

        return $this->recommendHumanReview(
            context: $context,

            support: $support
        );
    }

    /**
     * @return Collection<int, ProjectDecisionEvidence>
     */
    private function recordedReviewSignals(
        ProjectDecisionContext $context
    ): Collection {
        $evidence =
            collect();

        if ($context->openCriticalRiskCount > 0) {
            $evidence->push(
                $this->countEvidence(
                    source: 'project.risks.open_critical',

                    description: sprintf(
                        '%d open critical project risk(s) are recorded for this exact project.',
                        $context->openCriticalRiskCount
                    ),

                    count: $context->openCriticalRiskCount,

                    projectId: $context->projectId
                )
            );
        }

        if ($context->openHighRiskCount > 0) {
            $evidence->push(
                $this->countEvidence(
                    source: 'project.risks.open_high',

                    description: sprintf(
                        '%d open high project risk(s) are recorded for this exact project.',
                        $context->openHighRiskCount
                    ),

                    count: $context->openHighRiskCount,

                    projectId: $context->projectId
                )
            );
        }

        if ($context->overdueDeliverableCount > 0) {
            $evidence->push(
                $this->countEvidence(
                    source: 'project.deliverables.overdue_incomplete',

                    description: sprintf(
                        '%d overdue incomplete project deliverable(s) are recorded for this exact project.',
                        $context->overdueDeliverableCount
                    ),

                    count: $context->overdueDeliverableCount,

                    projectId: $context->projectId
                )
            );
        }

        if ($context->updatesWithBlockersCount > 0) {
            $evidence->push(
                $this->countEvidence(
                    source: 'project.updates.recorded_blockers',

                    description: sprintf(
                        '%d project update(s) contain recorded blockers for this exact project.',
                        $context->updatesWithBlockersCount
                    ),

                    count: $context->updatesWithBlockersCount,

                    projectId: $context->projectId
                )
            );
        }

        if ($context->updatesWithRisksCount > 0) {
            $evidence->push(
                $this->countEvidence(
                    source: 'project.updates.recorded_risks',

                    description: sprintf(
                        '%d project update(s) contain recorded risks for this exact project.',
                        $context->updatesWithRisksCount
                    ),

                    count: $context->updatesWithRisksCount,

                    projectId: $context->projectId
                )
            );
        }

        return $evidence->values();
    }

    /**
     * @return Collection<int, array{
     *     constraint: ProjectDecisionConstraint,
     *     missing_truth: string
     * }>
     */
    private function unresolvedEvidenceConditions(
        ProjectDecisionContext $context
    ): Collection {
        $conditions =
            collect();

        if ($context->latestUpdateAt === null) {
            $conditions->push([
                'constraint' => new ProjectDecisionConstraint(
                    code: 'project_update_missing',

                    description: 'No project update is recorded for this exact project. Human review may proceed, but the absence of a recorded update must remain explicit.',

                    type: ProjectDecisionConstraint::CONDITION,

                    source: 'project.updates',

                    confidence: 100,

                    metadata: [
                        'project_id' => $context->projectId,
                    ]
                ),

                'missing_truth' => 'No project update is recorded for this exact project.',
            ]);
        }

        if ($context->openUpdateRequestCount > 0) {
            $conditions->push([
                'constraint' => new ProjectDecisionConstraint(
                    code: 'project_update_requests_open',

                    description: sprintf(
                        '%d project update request(s) remain open. Human review may proceed, but the outstanding requested evidence must remain explicit.',
                        $context->openUpdateRequestCount
                    ),

                    type: ProjectDecisionConstraint::CONDITION,

                    source: 'project.update_requests.open',

                    confidence: 100,

                    metadata: [
                        'project_id' => $context->projectId,

                        'open_update_request_count' => $context->openUpdateRequestCount,
                    ]
                ),

                'missing_truth' => sprintf(
                    '%d project update request(s) remain open and the requested project evidence is not yet resolved.',
                    $context->openUpdateRequestCount
                ),
            ]);
        }

        return $conditions->values();
    }

    private function recommendHumanReview(
        ProjectDecisionContext $context,
        Collection $support,
    ): ProjectDecision {
        $evidence =
            $support
                ->push(
                    $this->projectContextEvidence(
                        $context
                    )
                )
                ->values();

        return new ProjectDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: ProjectDecision::RECOMMENDED,

            recommendation: 'Proceed to human project review of the recorded evidence for this exact project.',

            rationale: 'One or more explicit Project OS V1 review signals are recorded for this exact project, and no V1 evidence condition remains unresolved. This recommends human review only; it does not classify project health, assign priority, create an action or execute a response.',

            evidence: $evidence,

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $context->observedAt
        );
    }

    private function recommendConditionalHumanReview(
        ProjectDecisionContext $context,
        Collection $support,
        Collection $conditions,
    ): ProjectDecision {
        $evidence =
            $support
                ->push(
                    $this->projectContextEvidence(
                        $context
                    )
                )
                ->values();

        return new ProjectDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: ProjectDecision::CONDITIONAL,

            recommendation: 'Proceed to human project review of the recorded evidence for this exact project while preserving the unresolved evidence conditions.',

            rationale: 'One or more explicit Project OS V1 review signals are recorded, but project evidence remains incomplete. Human review is supported only with that uncertainty preserved explicitly; Project OS does not convert the incomplete evidence into a health, priority or action conclusion.',

            evidence: $evidence,

            constraints: $conditions
                ->pluck(
                    'constraint'
                )
                ->values(),

            confidence: 100,

            missingTruth: $conditions
                ->pluck(
                    'missing_truth'
                )
                ->values(),

            asOf: $context->observedAt
        );
    }

    private function deferWithoutAffirmativeReviewSignal(
        ProjectDecisionContext $context
    ): ProjectDecision {
        $missingTruth =
            collect();

        if ($context->latestUpdateAt === null) {
            $missingTruth->push(
                'No project update is recorded for this exact project.'
            );
        }

        if ($context->openUpdateRequestCount > 0) {
            $missingTruth->push(
                sprintf(
                    '%d project update request(s) remain open and the requested project evidence is not yet resolved.',
                    $context->openUpdateRequestCount
                )
            );
        }

        $missingTruth->push(
            'Whether this project requires human project review despite having no recorded Project OS V1 review signal is not established.'
        );

        return new ProjectDecision(
            key: $context->request->key,

            question: $context->request->question,

            status: ProjectDecision::DEFERRED,

            recommendation: null,

            rationale: 'No affirmative Project OS V1 review signal is recorded for this exact project. Absence of those recorded signals does not establish that the project is healthy, complete, on track or exempt from human review, so the decision is deferred.',

            evidence: collect([
                $this->projectContextEvidence(
                    $context
                ),
            ]),

            constraints: collect([
                new ProjectDecisionConstraint(
                    code: 'affirmative_project_review_signal_missing',

                    description: 'No affirmative Project OS V1 review signal is recorded. V1 does not infer a negative human-review decision from that absence.',

                    type: ProjectDecisionConstraint::BLOCKING,

                    source: 'project.decision_context',

                    confidence: 100,

                    metadata: [
                        'project_id' => $context->projectId,
                    ]
                ),
            ]),

            confidence: 0,

            missingTruth: $missingTruth
                ->values(),

            asOf: $context->observedAt
        );
    }

    private function countEvidence(
        string $source,
        string $description,
        int $count,
        int $projectId,
    ): ProjectDecisionEvidence {
        return new ProjectDecisionEvidence(
            source: $source,

            description: $description,

            position: ProjectDecisionEvidence::SUPPORTS,

            confidence: 100,

            metadata: [
                'project_id' => $projectId,

                'count' => $count,
            ]
        );
    }

    private function projectContextEvidence(
        ProjectDecisionContext $context
    ): ProjectDecisionEvidence {
        return new ProjectDecisionEvidence(
            source: 'project.decision_context',

            description: 'Project OS evaluated the recorded factual context attributable to this exact project.',

            position: ProjectDecisionEvidence::CONTEXT,

            confidence: 100,

            metadata: [
                'project_id' => $context->projectId,

                'project_name' => $context->projectName,

                'project_status' => $context->projectStatus,

                'latest_update_at' => $context->latestUpdateAt
                    ?->toIso8601String(),

                'responded_update_request_count' => $context->respondedUpdateRequestCount,

                'client_commitment_count' => $context->clientCommitmentCount,

                'observed_at' => $context->observedAt
                    ->toIso8601String(),
            ]
        );
    }
}
