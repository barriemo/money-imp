<?php

namespace App\Domains\Imports\DTOs;

use Carbon\CarbonImmutable;

readonly class ImportedTransaction
{
    public function __construct(
        public CarbonImmutable $date,
        public float $amount,
        public string $description,
        public ?string $merchant = null,
        public ?string $reference = null,
        public string $currency = 'GBP',
        public array $raw = [],
    ) {}
}
