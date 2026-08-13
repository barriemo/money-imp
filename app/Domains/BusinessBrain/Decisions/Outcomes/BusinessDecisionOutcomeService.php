<?php

namespace App\Domains\BusinessBrain\Decisions\Outcomes;

use App\Domains\BusinessBrain\Decisions\BusinessDecision;
use App\Models\BusinessDecisionOutcome;
use Illuminate\Support\Collection;

class BusinessDecisionOutcomeService
{
    public function __construct(
        private BusinessDecisionFingerprint $fingerprints
    ) {}

    public function record(
        BusinessDecision $decision
    ): BusinessDecisionOutcome {
        $fingerprint =
            $this->fingerprints
                ->make(
                    $decision
                );

        $existing =
            BusinessDecisionOutcome::query()
                ->where(
                    'fingerprint',
                    $fingerprint
                )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'accepted',
                    ]
                )
                ->first();

        if ($existing) {
            $existing->update([
                'reason' => $decision->reason,

                'priority' => $decision->priority,

                'value' => $decision->value,
            ]);

            return $existing->refresh();
        }

        return BusinessDecisionOutcome::create([
            'fingerprint' => $fingerprint,

            'decision_type' => $decision->type,

            'client_id' => $decision->clientId,

            'client' => $decision->client,

            'action' => $decision->action,

            'reason' => $decision->reason,

            'priority' => $decision->priority,

            'value' => $decision->value,

            'status' => 'pending',
        ]);
    }

    public function accept(
        BusinessDecisionOutcome $outcome
    ): BusinessDecisionOutcome {
        if ($outcome->status !== 'pending') {
            throw new \LogicException(
                sprintf(
                    'Cannot accept recommendation in %s state.',
                    $outcome->status
                )
            );
        }

        $outcome->update([
            'status' => 'accepted',

            'decided_at' => now(),
        ]);

        return $outcome->refresh();
    }

    public function reject(
        BusinessDecisionOutcome $outcome,
        ?string $reason = null
    ): BusinessDecisionOutcome {
        if (
            ! in_array(
                $outcome->status,
                [
                    'pending',
                    'accepted',
                ],
                true
            )
        ) {
            throw new \LogicException(
                sprintf(
                    'Cannot reject recommendation in %s state.',
                    $outcome->status
                )
            );
        }

        $outcome->update([
            'status' => 'rejected',

            'outcome' => $reason,

            'decided_at' => $outcome->decided_at
                ?? now(),
        ]);

        return $outcome->refresh();
    }

    public function complete(
        BusinessDecisionOutcome $outcome,
        string $result,
        ?float $financialResult = null
    ): BusinessDecisionOutcome {
        if (
            ! in_array(
                $outcome->status,
                [
                    'pending',
                    'accepted',
                ],
                true
            )
        ) {
            throw new \LogicException(
                sprintf(
                    'Cannot complete recommendation in %s state.',
                    $outcome->status
                )
            );
        }

        $outcome->update([
            'status' => 'completed',

            'outcome' => $result,

            'financial_result' => $financialResult,

            'decided_at' => $outcome->decided_at
                ?? now(),

            'completed_at' => now(),
        ]);

        return $outcome->refresh();
    }

    public function summary(): array
    {
        $query =
            BusinessDecisionOutcome::query();

        $completed =
            (clone $query)
                ->where(
                    'status',
                    'completed'
                );

        return [
            'total' => (clone $query)->count(),

            'pending' => (clone $query)
                ->where(
                    'status',
                    'pending'
                )
                ->count(),

            'accepted' => (clone $query)
                ->where(
                    'status',
                    'accepted'
                )
                ->count(),

            'rejected' => (clone $query)
                ->where(
                    'status',
                    'rejected'
                )
                ->count(),

            'completed' => (clone $query)
                ->where(
                    'status',
                    'completed'
                )
                ->count(),

            'financial_result' => (float) $completed
                ->sum(
                    'financial_result'
                ),
        ];
    }

    public function recordToday(
        Collection $decisions
    ): Collection {
        return $decisions
            ->map(
                fn (BusinessDecision $decision) => $this->record(
                    $decision
                )
            )
            ->values();
    }
}
