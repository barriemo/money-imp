<?php

namespace App\Domains\Reasoning;

class Question
{
    public function __construct(
        public string $type,
        public array $context = []
    ) {}

    public static function revenueRecovery(): self
    {
        return new self(
            type: 'revenue_recovery'
        );
    }
}
