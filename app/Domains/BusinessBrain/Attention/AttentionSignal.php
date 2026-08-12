<?php

namespace App\Domains\BusinessBrain\Attention;

class AttentionSignal
{
    public function __construct(
        public string $type,

        public string $client,

        public int $priority,

        public float $value,

        public string $reason,
    ) {}
}
