<?php

namespace App\Domains\VATIntelligence;

class VATExposureBuilder
{
    public function build(
        VATPosition $position
    ): VATExposure {
        $liability =
            $position->liability();

        return new VATExposure(
            liability: $liability,

            priority: $this->priority(
                $liability
            ),

            reason: 'VAT liability requires cash planning.'
        );
    }

    private function priority(
        float $liability
    ): int {
        return min(
            100,
            (int) round(
                $liability / 300
            )
        );
    }
}
