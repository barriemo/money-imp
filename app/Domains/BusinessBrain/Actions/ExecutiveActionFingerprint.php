<?php

namespace App\Domains\BusinessBrain\Actions;

use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;

class ExecutiveActionFingerprint
{
    public function make(
        ExecutiveReasoning $reasoning
    ): string {
        return hash(
            'sha256',
            implode(
                '|',
                [
                    $reasoning->type,
                    $reasoning->clientId ?? 'business',
                    $reasoning->title,
                    $reasoning->recommendedAction,
                ]
            )
        );
    }
}
