<?php

namespace App\Domains\BusinessBrain;

class BusinessQuestion
{
    public function __construct(
        public string $question,
        public string $reason,
        public int $priority,
        public array $context = []
    ) {}
}
