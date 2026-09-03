<?php

namespace App\Domains\BusinessBrain\Investigation\EvidenceBus;

use App\Domains\BusinessBrain\Investigation\Reassessment\EvidenceTrigger;
use App\Domains\BusinessBrain\Investigation\Reassessment\InvestigationReassessmentCoordinator;
use App\Domains\BusinessBrain\Signals\CeoSignalEvidenceReassessmentService;
use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class InvestigationEvidenceBus
{
    public function __construct(
        private InvestigationReassessmentCoordinator $reassessment,

        private CeoSignalEvidenceReassessmentService $ceoSignals
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
        /*
         * CEO-origin questions use their own evidence-safe
         * reassessment path.
         *
         * Run it before the older hypothesis reassessment path
         * so new evidence can evolve the CEO answer while its
         * human claim remains unverified.
         */
        $this->ceoSignals
            ->reassess(
                $change
            );

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
