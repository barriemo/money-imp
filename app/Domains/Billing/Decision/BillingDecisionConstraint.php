<?php

namespace App\Domains\Billing\Decision;

use InvalidArgumentException;

final class BillingDecisionConstraint
{
    public const TYPE_BLOCKING = 'blocking';

    public const TYPE_CONDITION = 'condition';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly array $metadata = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Billing decision constraint key must not be empty.'
            );
        }

        if (trim($this->label) === '') {
            throw new InvalidArgumentException(
                'Billing decision constraint label must not be empty.'
            );
        }

        if (! in_array(
            $this->type,
            [
                self::TYPE_BLOCKING,
                self::TYPE_CONDITION,
            ],
            true,
        )) {
            throw new InvalidArgumentException(
                'Billing decision constraint type is invalid.'
            );
        }
    }
}
