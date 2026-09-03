<?php

namespace App\Domains\BusinessBrain\Signals;

final readonly class CeoSignalReassessmentResult
{
    public function __construct(
        public string $entryId,

        public string $status,

        public bool $changed,

        public ?string $previousState,

        public ?string $currentState,

        public ?string $eventId,
    ) {}
}
