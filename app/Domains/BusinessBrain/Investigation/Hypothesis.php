<?php

namespace App\Domains\BusinessBrain\Investigation;

class Hypothesis
{
    public function __construct(
        public string $statement,

        public string $subjectType,

        public string $subjectId,

        public ?string $subjectName = null,

        public string $assertedBy = 'user',

        public int $confidence = 50,

        public string $status = 'untested',

        public array $metadata = []
    ) {}
}
