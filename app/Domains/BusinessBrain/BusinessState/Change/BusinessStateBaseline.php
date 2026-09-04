<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BusinessStateBaseline
{
    public function __construct(
        public Collection $metrics,
        public CarbonImmutable $asOf,
    ) {
        $keys = $this->metrics->map(
            fn (BusinessStateMetric $metric): string => $metric->key()
        );

        if (
            $keys->unique()->count()
            !== $keys->count()
        ) {
            throw new InvalidArgumentException(
                'Business state baseline contains duplicate metric keys.'
            );
        }
    }

    public function keyedMetrics(): Collection
    {
        return $this->metrics
            ->keyBy(
                fn (BusinessStateMetric $metric): string => $metric->key()
            )
            ->sortKeys();
    }
}
