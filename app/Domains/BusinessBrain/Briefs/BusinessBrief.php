<?php

namespace App\Domains\BusinessBrain\Briefs;

class BusinessBrief
{
    public function __construct(
        public readonly string $business,

        public readonly array $priorities = [],

        public readonly array $actions = []
    ) {}
}
