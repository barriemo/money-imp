<?php

namespace App\Domains\Cfo\Decision;

use InvalidArgumentException;

class CfoDecisionConstraint
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
                'CFO decision constraint code cannot be empty.'
            );
        }

        if (trim($this->description) === '') {
            throw new InvalidArgumentException(
                'CFO decision constraint description cannot be empty.'
            );
        }

        if (trim($this->source) === '') {
            throw new InvalidArgumentException(
                'CFO decision constraint source cannot be empty.'
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
                'Invalid CFO decision constraint type.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'CFO decision constraint confidence must be between 0 and 100.'
            );
        }
    }
}
