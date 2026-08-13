<?php

namespace App\Domains\BusinessBrain\Interrogation;

class BusinessQuestion
{
    public function __construct(
        public string $question
    ) {}

    public function normalised(): string
    {
        return strtolower(
            trim(
                $this->question
            )
        );
    }
}
