<?php

namespace App\Domains\BusinessBrain\Signals;

final readonly class CeoSignalCurrentAnswer
{
    public function __construct(
        public string $entryId,

        public string $question,

        public string $askedAtLabel,

        public string $status,

        public string $statusLabel,

        public string $headline,

        public string $summary,

        public string $nextStep,

        public string $truthBoundary,
    ) {}
}
