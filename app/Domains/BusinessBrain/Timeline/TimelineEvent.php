<?php

namespace App\Domains\BusinessBrain\Timeline;

use Carbon\Carbon;

final readonly class TimelineEvent
{
    public function __construct(
        public Carbon $occurredAt,

        public string $type,

        public string $title,

        public string $description,

        public ?float $value,

        public int $importance,

        public string $identity,

        public array $metadata = []
    ) {}
}
