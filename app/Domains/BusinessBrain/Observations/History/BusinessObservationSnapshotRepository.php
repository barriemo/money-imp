<?php

namespace App\Domains\BusinessBrain\Observations\History;

use App\Domains\BusinessBrain\Observations\BusinessObservation;
use App\Models\BusinessObservationSnapshotRecord;

class BusinessObservationSnapshotRepository
{
    public function store(
        BusinessObservationSnapshot $snapshot
    ): void {
        BusinessObservationSnapshotRecord::create([
            'generated_at' => $snapshot->generatedAt,

            'observations' => $snapshot
                ->observations
                ->map(
                    fn (BusinessObservation $observation) => [
                        'type' => $observation->type,

                        'title' => $observation->title,

                        'message' => $observation->message,

                        'priority' => $observation->priority,

                        'client_id' => $observation->clientId,

                        'client' => $observation->client,

                        'value' => $observation->value,

                        'confidence' => $observation->confidence,
                    ]
                )
                ->values()
                ->all(),
        ]);
    }

    public function latest(): ?BusinessObservationSnapshot
    {
        $record =
            BusinessObservationSnapshotRecord::query()
                ->latest(
                    'generated_at'
                )
                ->first();

        if (! $record) {
            return null;
        }

        return new BusinessObservationSnapshot(
            observations: collect(
                $record->observations
            )
                ->map(
                    fn (array $observation) => new BusinessObservation(
                        type: $observation['type'],

                        title: $observation['title'],

                        message: $observation['message'],

                        priority: (int) $observation['priority'],

                        clientId: $observation['client_id'],

                        client: $observation['client'],

                        value: isset(
                            $observation['value']
                        )
                            ? (float) $observation['value']
                            : null,

                        confidence: (int) $observation['confidence']
                    )
                ),

            generatedAt: $record->generated_at
        );
    }
}
