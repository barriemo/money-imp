<?php

namespace App\Domains\BusinessBrain\Project\Delivery;

class DeliverableHealth
{
    public function __construct(
        public string $status,

        public string $reason
    ) {}
}
