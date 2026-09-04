<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use App\Models\BusinessStateBaselineSnapshotRecord;
use Carbon\CarbonImmutable;

class BusinessStateBaselineSnapshotRepository
{
    public function store(
        BusinessStateBaseline $baseline
    ): void {
        BusinessStateBaselineSnapshotRecord::create([
            'as_of' => $baseline->asOf,

            'metrics' => $baseline->metrics
                ->map(
                    fn (BusinessStateMetric $metric): array => [
                        'domain' => $metric->domain,

                        'metric' => $metric->metric,

                        'scope' => $metric->scope,

                        'client_id' => $metric->clientId,

                        'client' => $metric->client,

                        'source' => $metric->source,

                        'known' => $metric->known,

                        'value' => $metric->value,
                    ]
                )
                ->values()
                ->all(),
        ]);
    }

    public function latestBefore(
        CarbonImmutable $asOf
    ): ?BusinessStateBaseline {
        $record =
            BusinessStateBaselineSnapshotRecord::query()
                ->where(
                    'as_of',
                    '<',
                    $asOf
                )
                ->orderByDesc(
                    'as_of'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->first();

        if (! $record) {
            return null;
        }

        return new BusinessStateBaseline(
            metrics: collect(
                $record->metrics
            )
                ->map(
                    fn (array $metric): BusinessStateMetric => new BusinessStateMetric(
                        domain: $metric['domain'],

                        metric: $metric['metric'],

                        scope: $metric['scope'],

                        clientId: $metric['client_id']
                                ?? null,

                        client: $metric['client']
                                ?? null,

                        source: $metric['source'],

                        known: (bool) $metric['known'],

                        value: $metric['value']
                                ?? null
                    )
                ),

            asOf: CarbonImmutable::instance(
                $record->as_of
            )
        );
    }
}
