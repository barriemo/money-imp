<?php

namespace App\Domains\Reasoning;

use Illuminate\Support\Collection;

class Answer
{
    public function __construct(
        public string $questionType,
        public string $summary,
        public Collection $evidence,
        public int $confidence,
        public array $data = []
    ) {}
}
