<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Models\BusinessMemoryEntry;
use Illuminate\Support\Collection;

final class CeoSignalEvidenceReassessmentService
{
    public function __construct(
        private readonly CeoSignalReassessmentService $reassessment,
    ) {}

    /**
     * Reassess existing CEO questions affected by a financial
     * evidence change.
     *
     * This does not create new questions, investigations,
     * verdicts or truth. CeoSignalReassessmentService remains
     * responsible for deciding whether materially new evidence
     * warrants an append-only reassessment event.
     *
     * @return Collection<int, CeoSignalReassessmentResult>
     */
    public function reassess(
        EvidenceChange $change
    ): Collection {
        if (
            ! in_array(
                $change->domain,
                [
                    'bank',
                    'accounting',
                ],
                true
            )
        ) {
            return collect();
        }

        return BusinessMemoryEntry::query()
            ->where(
                'source',
                'ceo_signal_box'
            )
            ->orderBy(
                'occurred_at'
            )
            ->get()
            ->filter(
                function (
                    BusinessMemoryEntry $entry
                ) use ($change): bool {
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
                    ) {
                        return false;
                    }

                    if (
                        $change->subjectType === 'client'
                        && $change->subjectId !== null
                    ) {
                        return (
                            $routing[
                                'subject_id'
                            ] ?? null
                        ) === $change->subjectId;
                    }

                    return true;
                }
            )
            ->map(
                fn (
                    BusinessMemoryEntry $entry
                ): CeoSignalReassessmentResult => $this->reassessment
                    ->reassess(
                        $entry
                    )
            )
            ->filter(
                fn (
                    CeoSignalReassessmentResult $result
                ): bool => $result->status
                    !== 'not_applicable'
            )
            ->values();
    }
}
