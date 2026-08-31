<?php

namespace App\Domains\BusinessBrain\Actions;

use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;

class ExecutiveActionPromotionPolicy
{
    public function shouldPromote(
        ExecutiveReasoning $reasoning
    ): bool {
        if (
            $reasoning->type === 'financial_opportunity'
            && $reasoning->clientId !== null
        ) {
            return true;
        }

        return in_array(
            $reasoning->type,
            [
                'cash_management',
                'client_advocacy',
            ],
            true
        );
    }
}
