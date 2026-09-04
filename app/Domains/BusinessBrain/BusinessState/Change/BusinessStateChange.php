<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BusinessStateChange
{
    public const BECAME_KNOWN = 'became_known';

    public const BECAME_UNKNOWN = 'became_unknown';

    public const INCREASED = 'increased';

    public const DECREASED = 'decreased';

    public function __construct(
        public BusinessStateMetric $previous,
        public BusinessStateMetric $current,
        public string $kind,
        public CarbonImmutable $previousAsOf,
        public CarbonImmutable $currentAsOf,
    ) {
        if (
            ! in_array(
                $this->kind,
                [
                    self::BECAME_KNOWN,
                    self::BECAME_UNKNOWN,
                    self::INCREASED,
                    self::DECREASED,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported business state change kind.'
            );
        }
    }
}
