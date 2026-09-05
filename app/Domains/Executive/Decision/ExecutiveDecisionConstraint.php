<?php

namespace App\Domains\Executive\Decision;

use InvalidArgumentException;

class ExecutiveDecisionConstraint
{
    public const BLOCKING =
        'blocking';

    public const CONDITION =
        'condition';

    public const TYPES = [
        self::BLOCKING,
        self::CONDITION,
    ];

    public function __construct(
        public string $code,
        public string $description,
        public string $type,
        public string $source,
        public int $confidence,
        public array $metadata = [],
    ) {
        if (trim($this->code) === '') {
            throw new InvalidArgumentException(
                'Executive decision constraint code cannot be empty.'
            );
        }

        if (trim($this->description) === '') {
            throw new InvalidArgumentException(
                'Executive decision constraint description cannot be empty.'
            );
        }

        if (trim($this->source) === '') {
            throw new InvalidArgumentException(
                'Executive decision constraint source cannot be empty.'
            );
        }

        if (
            ! in_array(
                $this->type,
                self::TYPES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid executive decision constraint type.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'Executive decision constraint confidence must be between 0 and 100.'
            );
        }
    }
}
