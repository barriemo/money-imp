<?php

namespace App\Domains\BusinessBrain\Attention\Context;

class AttentionContext
{
    public function __construct(
        public string $client,

        public mixed $recovery = null,

        public mixed $allocation = null,

        public mixed $vat = null,
    ) {}
}
