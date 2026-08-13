<?php

namespace App\Domains\BusinessBrain\Decisions\Outcomes;

use App\Domains\BusinessBrain\Decisions\BusinessDecision;

class BusinessDecisionFingerprint
{
    public function make(
        BusinessDecision $decision
    ): string {
        return hash(
            'sha256',
            implode('|', [
                $decision->type,
                $decision->clientId,
                $decision->action,
            ])
        );
    }
}
