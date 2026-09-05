<?php

namespace App\Domains\Project\Decision;

use InvalidArgumentException;

class ProjectDecisionService
{
    public function __construct(
        private ProjectDecisionContextService $contexts,
        private ProjectReviewReadinessPolicy $review,
    ) {}

    public function decide(
        ProjectDecisionRequest $request
    ): ProjectDecision {
        /*
         * Project OS V1 exposes exactly one authoritative policy.
         *
         * Unsupported decision types fail before context assembly.
         * Routing does not select priorities, inspect legacy health,
         * create actions or provide generic reasoning.
         */
        if (! $this->review->supports($request)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Project OS v1 has no authoritative policy for decision request %s.',
                    $request->key
                )
            );
        }

        $context =
            $this->contexts
                ->forDecision(
                    $request
                );

        return $this->review
            ->decide(
                $context
            );
    }
}
