<?php

namespace App\Domains\BusinessBrain\Signals;

use App\Models\BusinessMemoryEntry;
use Illuminate\Support\Collection;

final class CeoSignalCurrentAnswerService
{
    public function __construct(
        private readonly CeoSignalFindingService $findings,
    ) {}

    public function current(
        string|int $userId,
        int $limit = 5
    ): Collection {
        $limit =
            max(
                1,
                min(
                    20,
                    $limit
                )
            );

        return BusinessMemoryEntry::query()
            ->where(
                'source',
                'ceo_signal_box'
            )
            ->where(
                'metadata->submitted_by_user_id',
                $userId
            )
            ->orderByDesc(
                'occurred_at'
            )
            ->orderByDesc(
                'id'
            )
            ->limit(
                $limit
            )
            ->get()
            ->map(
                fn (BusinessMemoryEntry $entry) => $this->answer(
                    $entry
                )
            )
            ->values();
    }

    private function answer(
        BusinessMemoryEntry $entry
    ): CeoSignalCurrentAnswer {
        $finding =
            $this->findings
                ->forEntry(
                    $entry
                );

        if ($finding) {
            return new CeoSignalCurrentAnswer(
                entryId: $entry->id,

                question: $entry->content,

                askedAtLabel: $entry->occurred_at
                    ?->diffForHumans()
                    ?? 'Recently',

                status: $finding->state,

                statusLabel: $this->findingStatusLabel(
                    $finding->state
                ),

                headline: $finding->headline,

                summary: $finding->summary,

                nextStep: $finding->nextStep,

                truthBoundary: $finding->truthBoundary
            );
        }

        return $this->withoutFinding(
            $entry
        );
    }

    private function withoutFinding(
        BusinessMemoryEntry $entry
    ): CeoSignalCurrentAnswer {
        $routing =
            $entry->metadata[
                'routing'
            ] ?? [];

        $routingStatus =
            (string) (
                $routing[
                    'status'
                ]
                ?? 'unrouted'
            );

        [
            $statusLabel,
            $headline,
            $nextStep,
        ] =
            match ($routingStatus) {
                'unresolved_subject' => [
                    'Needs clarification',

                    'Money Imp needs a clearer subject before it can answer this.',

                    'Clarify which client or business subject this question concerns.',
                ],

                'resolved_subject_no_current_ledger_evidence' => [
                    'Waiting for evidence',

                    'Money Imp matched the subject but does not yet have supported ledger evidence to answer it.',

                    'Complete the relevant ledger evidence before drawing a conclusion.',
                ],

                'unrouted' => [
                    'Not yet routed',

                    'Money Imp has captured this question but cannot yet route it to a supported investigation.',

                    'Keep the question open until the Brain has a supported domain and evidence path.',
                ],

                default => [
                    'Investigating',

                    'Money Imp is still investigating this question.',

                    'Continue gathering supported evidence before forming a conclusion.',
                ],
            };

        return new CeoSignalCurrentAnswer(
            entryId: $entry->id,

            question: $entry->content,

            askedAtLabel: $entry->occurred_at
                ?->diffForHumans()
                ?? 'Recently',

            status: $routingStatus,

            statusLabel: $statusLabel,

            headline: $headline,

            summary: 'Your original input remains unverified. Money Imp does not yet have an evidence-backed answer to present.',

            nextStep: $nextStep,

            truthBoundary: 'A CEO signal records what a human asked or observed. It does not become business truth until supported evidence establishes a conclusion.'
        );
    }

    private function findingStatusLabel(
        string $state
    ): string {
        return match ($state) {
            'investigation_requires_attention' => 'Investigating',

            'candidate_requires_verification' => 'Evidence to verify',

            'weak_evidence_requires_review' => 'Evidence needs review',

            'evidence_coverage_incomplete' => 'Bank evidence incomplete',

            'evidence_missing' => 'Waiting for bank evidence',

            'waiting_for_payment_evidence' => 'Searching payment evidence',

            default => 'Investigating',
        };
    }
}
