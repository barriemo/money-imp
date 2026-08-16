<?php

namespace App\Domains\BusinessBrain\Investigation\EvidenceBus;

use App\Domains\BusinessBrain\Investigation\Reassessment\EvidenceTrigger;
use App\Domains\BusinessBrain\Investigation\Reassessment\InvestigationReassessmentCoordinator;
use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class InvestigationEvidenceBus
{
    public function __construct(
        private InvestigationReassessmentCoordinator $reassessment
    ) {}

    /**
     * @return Collection<int, InvestigationCase>
     */
    public function publish(
        EvidenceChange $change
    ): Collection {
        return match ($change->domain) {
            'bank',
            'accounting' => $this->financialEvidenceChanged(
                $change
            ),

            default => collect(),
        };
    }

    /**
     * @return Collection<int, InvestigationCase>
     */
    private function financialEvidenceChanged(
        EvidenceChange $change
    ): Collection {
        $trigger =
            new EvidenceTrigger(
                domain: $change->domain,
                type: $change->type,
                metadata: $change->metadata
            );

        if (
            $change->subjectType === 'client'
            && $change->subjectId !== null
        ) {
            return $this->reassessment
                ->reassessOpenCases(
                    type: 'client_ledger',
                    subjectType: 'client',
                    subjectId: $change->subjectId,
                    trigger: $trigger
                );
        }

        return $this->reassessment
            ->reassessOpenCases(
                type: 'client_ledger',
                trigger: $trigger
            );
    }
}
