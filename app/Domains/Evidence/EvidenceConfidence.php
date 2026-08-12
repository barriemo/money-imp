<?php

namespace App\Domains\Evidence;

class EvidenceConfidence
{
    public function clamp(
        int $confidence
    ): int {
        return max(
            0,
            min(
                100,
                $confidence
            )
        );
    }
}
