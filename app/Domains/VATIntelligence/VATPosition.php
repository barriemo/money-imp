<?php

namespace App\Domains\VATIntelligence;

class VATPosition
{
    public function __construct(
        public float $vatCollected,

        public float $vatPaid,

        public \DateTimeInterface $dueDate
    ) {}

    public function liability(): float
    {
        return max(
            0,
            $this->vatCollected - $this->vatPaid
        );
    }
}
