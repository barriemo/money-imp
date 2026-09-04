<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use InvalidArgumentException;

class BusinessStateExplanationEvidence
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
        if (
            trim($this->source) === ''
            || trim($this->description) === ''
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence requires source and description.'
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
                'Unsupported explanation evidence position.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence confidence must be between 0 and 100.'
            );
        }
    }
}
