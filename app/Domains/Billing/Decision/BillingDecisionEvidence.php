<?php

namespace App\Domains\Billing\Decision;

use InvalidArgumentException;

final class BillingDecisionEvidence
{
    public const POSITION_SUPPORTS = 'supports';

    public const POSITION_CONTRADICTS = 'contradicts';

    public const POSITION_CONTEXT = 'context';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $position,
        public readonly int $confidence,
        public readonly array $metadata = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Billing decision evidence key must not be empty.'
            );
        }

        if (trim($this->label) === '') {
            throw new InvalidArgumentException(
                'Billing decision evidence label must not be empty.'
            );
        }

        if (! in_array(
            $this->position,
            [
                self::POSITION_SUPPORTS,
                self::POSITION_CONTRADICTS,
                self::POSITION_CONTEXT,
            ],
            true,
        )) {
            throw new InvalidArgumentException(
                'Billing decision evidence position is invalid.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'Billing decision evidence confidence must be between 0 and 100.'
            );
        }
    }
}
