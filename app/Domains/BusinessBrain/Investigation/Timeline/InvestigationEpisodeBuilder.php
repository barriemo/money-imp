<?php

namespace App\Domains\BusinessBrain\Investigation\Timeline;

use App\Models\InvestigationCase;
use Illuminate\Support\Collection;

class InvestigationEpisodeBuilder
{
    /**
     * @return Collection<int, InvestigationEpisode>
     */
    public function build(
        InvestigationCase $case
    ): Collection {
        $case->loadMissing(
            'events'
        );

        $events =
            $case->events
                ->sortBy(
                    'occurred_at'
                )
                ->values();

        $correlated =
            $events
                ->filter(
                    fn ($event) => isset(
                        $event->payload['correlation_id']
                    )
                )
                ->groupBy(
                    fn ($event) => $event->payload['correlation_id']
                )
                ->map(
                    fn (Collection $episodeEvents, string $correlationId) => $this->correlatedEpisode(
                        $correlationId,
                        $episodeEvents
                    )
                )
                ->values();

        $legacyEvents =
            $events
                ->reject(
                    fn ($event) => isset(
                        $event->payload['correlation_id']
                    )
                )
                ->values();

        $legacy =
            $this->legacyEpisodes(
                $legacyEvents
            );

        return $legacy
            ->concat(
                $correlated
            )
            ->sortBy(
                fn (InvestigationEpisode $episode) => $episode->events
                    ->first()
                    ?->occurred_at
            )
            ->values();
    }

    private function correlatedEpisode(
        string $correlationId,
        Collection $events
    ): InvestigationEpisode {
        $trigger =
            $events
                ->firstWhere(
                    'type',
                    'evidence_changed'
                );

        $closed =
            $events
                ->firstWhere(
                    'type',
                    'case_closed'
                );

        return new InvestigationEpisode(
            correlationId: $correlationId,

            trigger: $trigger?->description,

            events: $events
                ->sortBy(
                    'occurred_at'
                )
                ->values(),

            claimChanges: $events
                ->whereIn(
                    'type',
                    [
                        'claim_assessed',
                        'claim_changed',
                    ]
                )
                ->values(),

            hypothesisChanges: $events
                ->whereIn(
                    'type',
                    [
                        'hypothesis_assessed',
                        'hypothesis_changed',
                    ]
                )
                ->values(),

            outcome: $closed?->description,

            legacy: false
        );
    }

    /**
     * @return Collection<int, InvestigationEpisode>
     */
    private function legacyEpisodes(
        Collection $events
    ): Collection {
        if ($events->isEmpty()) {
            return collect();
        }

        /*
         * Legacy events pre-date explicit causal correlation IDs.
         * Keep them together as a readable historical episode rather
         * than pretending we know more about causality than we do.
         */
        return collect([
            new InvestigationEpisode(
                correlationId: null,

                trigger: null,

                events: $events,

                claimChanges: $events
                    ->whereIn(
                        'type',
                        [
                            'claim_assessed',
                            'claim_changed',
                        ]
                    )
                    ->unique(
                        fn ($event) => $event->type
                            .'|'
                            .$event->description
                    )
                    ->values(),

                hypothesisChanges: $events
                    ->whereIn(
                        'type',
                        [
                            'hypothesis_assessed',
                            'hypothesis_changed',
                        ]
                    )
                    ->unique(
                        fn ($event) => $event->type
                            .'|'
                            .$event->description
                    )
                    ->values(),

                outcome: $events
                    ->firstWhere(
                        'type',
                        'case_closed'
                    )
                    ?->description,

                legacy: true
            ),
        ]);
    }
}
