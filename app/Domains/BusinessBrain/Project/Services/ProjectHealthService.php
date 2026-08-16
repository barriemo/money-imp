<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Domains\BusinessBrain\Project\Health\ProjectCommitmentRiskDetector;
use App\Domains\BusinessBrain\Project\Health\ProjectHealthAssessment;
use App\Domains\BusinessBrain\Project\Health\ProjectUpdateHealthDetector;
use App\Models\Project;

class ProjectHealthService
{
    public function __construct(
        private ProjectCommitmentRiskDetector $commitmentRisk,

        private ProjectUpdateHealthDetector $updates
    ) {}

    public function assess(
        Project $project
    ): ProjectHealthAssessment {
        if (
            $project->risks()
                ->where(
                    'status',
                    'open'
                )
                ->whereIn(
                    'severity',
                    [
                        'high',
                        'critical',
                    ]
                )
                ->exists()
        ) {
            return new ProjectHealthAssessment(
                status: 'blocked',

                reasons: [
                    'High priority project risk exists.',
                ],

                recommendedAction: 'Resolve open project risks.'
            );
        }

        if (
            $this->commitmentRisk
                ->hasClientCommitmentRisk(
                    $project
                )
        ) {
            return new ProjectHealthAssessment(
                status: 'blocked',

                reasons: [
                    'Client commitment is at risk.',
                ],

                recommendedAction: 'Review client commitment and agree recovery plan.'
            );
        }

        if (
            $this->updates
                ->hasBlockedUpdate(
                    $project
                )
        ) {
            return new ProjectHealthAssessment(
                status: 'at_risk',

                reasons: [
                    'Project has insufficient recent progress evidence.',
                ],

                recommendedAction: 'Request a current project update.'
            );
        }

        if (
            $this->updates
                ->needsUpdate(
                    $project
                )
        ) {
            return new ProjectHealthAssessment(
                status: 'at_risk',

                reasons: [
                    'Project has overdue deliverables.',
                ],

                recommendedAction: 'Review overdue deliverables and recovery actions.'
            );
        }

        if (
            $project->deliverables()
                ->whereNull(
                    'completed_at'
                )
                ->where(
                    'due_date',
                    '<',
                    now()
                )
                ->exists()
        ) {
            return 'at_risk';
        }

        return new ProjectHealthAssessment(
            status: 'healthy'
        );
    }
}
