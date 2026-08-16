<?php

namespace App\Domains\BusinessBrain\Investigation;

interface EvidenceCollector
{
    /**
     * @return array<int, EvidenceItem>
     */
    public function collect(
        Hypothesis $hypothesis
    ): array;
}
