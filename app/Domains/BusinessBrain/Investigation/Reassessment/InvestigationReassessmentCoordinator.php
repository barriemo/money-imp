<?php

namespace App\Domains\BusinessBrain\Investigation\Reassessment;

use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class InvestigationReassessmentCoordinator
{
    public function __construct(
        private InvestigationReassessmentService $reassessment
    ) {}

    /**
     * @return Collection<int, InvestigationCase>
     */
    public function reassessOpenCases(
        ?string $type = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?EvidenceTrigger $trigger = null
    ): Collection {
        $query =
            InvestigationCase::query()
                ->whereIn(
                    'status',
                    [
                        'open',
                        'testing',
                        'waiting',
                    ]
                );

        if ($type !== null) {
            $query->where(
                'type',
                $type
            );
        }

        if ($subjectType !== null) {
            $query->where(
                'subject_type',
                $subjectType
            );
        }

        if ($subjectId !== null) {
            $query->where(
                'subject_id',
                $subjectId
            );
        }

        return $query
            ->orderBy(
                'opened_at'
            )
            ->get()
            ->map(
                fn (InvestigationCase $case) => $this->reassessment
                    ->reassess(
                        $case,
                        $trigger
                    )
            );
    }
}
