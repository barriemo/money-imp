<?php

namespace App\Domains\BusinessBrain\Observations;

use App\Domains\BusinessBrain\Decisions\BusinessDecision;
use App\Domains\BusinessBrain\Decisions\BusinessDecisionService;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationChangeDetector;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshot;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshotRepository;
use Illuminate\Support\Collection;

class BusinessObservationService
{
    public function __construct(
        private BusinessDecisionService $decisions,

        private BusinessObservationSnapshotRepository $snapshots,

        private BusinessObservationChangeDetector $changes
    ) {}

    public function current(): Collection
    {
        return $this->decisions
            ->today()
            ->map(
                fn (BusinessDecision $decision) => $this->fromDecision(
                    $decision
                )
            )
            ->sortByDesc(
                'priority'
            )
            ->values();
    }

    public function hasSnapshot(): bool
    {
        return $this->snapshots
            ->latest() !== null;
    }

    public function observe(): Collection
    {
        $previous =
            $this->snapshots
                ->latest();

        $current =
            new BusinessObservationSnapshot(
                observations: $this->current(),

                generatedAt: now()
            );

        $this->snapshots
            ->store(
                $current
            );

        if (! $previous) {
            return collect();
        }

        return $this->changes
            ->compare(
                $previous,
                $current
            );
    }

    private function fromDecision(
        BusinessDecision $decision
    ): BusinessObservation {
        return new BusinessObservation(
            type: $decision->type,

            title: $this->title(
                $decision
            ),

            message: $decision->reason,

            priority: $decision->priority,

            clientId: $decision->clientId,

            client: $decision->client,

            value: $decision->value,

            confidence: $decision->confidence
        );
    }

    private function title(
        BusinessDecision $decision
    ): string {
        return match ($decision->type) {
            'collections' => $decision->client.' has overdue money requiring attention',

            'billing_dormancy' => $decision->client.' has gone quiet commercially',

            'charlie_follow_up' => $decision->client.' has unresolved operational questions',

            'bank_matching' => $decision->client.' has unresolved banking evidence',

            'invoice_delivery' => $decision->client.' has delivered commercial value awaiting invoicing',

            'payment_evidence' => $decision->client.' has accounting payments that require bank verification',

            'delivery_evidence' => $decision->client.' has insufficient delivery evidence',

            default => $decision->client.' requires attention',
        };
    }
}
