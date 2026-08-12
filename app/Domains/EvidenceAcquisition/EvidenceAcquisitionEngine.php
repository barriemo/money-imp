<?php

namespace App\Domains\EvidenceAcquisition;

use App\Domains\EvidenceAcquisition\Contracts\EvidenceQuestionProvider;
use Illuminate\Support\Collection;

class EvidenceAcquisitionEngine
{
    /**
     * @param  array<int, EvidenceQuestionProvider>  $providers
     */
    public function __construct(
        private array $providers
    ) {}

    public function questions(): Collection
    {
        return collect(
            $this->providers
        )
            ->flatMap(
                fn (
                    EvidenceQuestionProvider $provider
                ) => $provider->questions()
            )
            ->sortByDesc(
                fn (
                    EvidenceQuestion $question
                ) => $question->priority
            )
            ->values();
    }
}
