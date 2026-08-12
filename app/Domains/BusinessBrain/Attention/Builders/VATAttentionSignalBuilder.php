<?php

namespace App\Domains\BusinessBrain\Attention\Builders;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\VATIntelligence\VATExposure;

class VATAttentionSignalBuilder
{
    public function build(
        string $entity,
        VATExposure $exposure
    ): ?AttentionSignal {
        if (
            $exposure->liability <= 0
        ) {
            return null;
        }

        return new AttentionSignal(
            type: 'vat_exposure',

            client: $entity,

            priority: $exposure->priority,

            value: $exposure->liability,

            reason: $exposure->reason
        );
    }
}
