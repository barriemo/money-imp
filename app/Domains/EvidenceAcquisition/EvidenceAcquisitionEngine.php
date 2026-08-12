<?php

namespace App\Domains\EvidenceAcquisition;

use App\Domains\EvidenceAcquisition\Contracts\EvidenceQuestionProvider;
use App\Domains\EvidenceAcquisition\Ranking\EvidenceQueueBuilder;
use Illuminate\Support\Collection;

class EvidenceAcquisitionEngine
{
    /**
     * @param  array<int, EvidenceQuestionProvider>  $providers
     */
    public function __construct(
        private array $providers,
        private EvidenceQueueBuilder $queueBuilder
    ) {}

    public function questions(): Collection
    {
        $questions =
            collect(
                $this->providers
            )
                ->flatMap(
                    fn (
                        EvidenceQuestionProvider $provider
                    ) => $provider->questions()
                )
                ->values();

        return $this->queueBuilder
            ->build(
                $questions
            );
    }
}
