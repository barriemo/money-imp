<?php

namespace App\Domains\BusinessBrain\Assertions;

class BusinessAssertion
{
    public function __construct(
        public string $subjectType,

        public string $subjectId,

        public ?string $subjectName,

        public string $statement,

        public string $status,

        public string $source,

        public array $metadata = []
    ) {}
}
