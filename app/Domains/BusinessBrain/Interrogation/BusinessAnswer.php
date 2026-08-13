<?php

namespace App\Domains\BusinessBrain\Interrogation;

class BusinessAnswer
{
    public function __construct(
        public string $question,

        public string $answer,

        public array $facts,

        public array $evidence,

        public int $confidence,

        public \DateTimeInterface $asOf
    ) {}
}
