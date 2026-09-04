<?php

namespace App\Domains\Cfo\Decision;

use InvalidArgumentException;

class CfoDecisionEvidence
{
    public const SUPPORTS =
        'supports';

    public const CONTRADICTS =
        'contradicts';

    public const CONTEXT =
        'context';

    public const POSITIONS = [
        self::SUPPORTS,
        self::CONTRADICTS,
        self::CONTEXT,
    ];

    public function __construct(
        public string $source,
        public string $description,
        public string $position,
        public int $confidence,
        public array $metadata = [],
    ) {
        if (trim($this->source) === '') {
            throw new InvalidArgumentException(
                'CFO decision evidence source cannot be empty.'
            );
        }

        if (trim($this->description) === '') {
            throw new InvalidArgumentException(
                'CFO decision evidence description cannot be empty.'
            );
        }

        if (
            ! in_array(
                $this->position,
                self::POSITIONS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid CFO decision evidence position.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'CFO decision evidence confidence must be between 0 and 100.'
            );
        }
    }
}
