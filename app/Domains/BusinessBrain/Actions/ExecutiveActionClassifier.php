<?php

namespace App\Domains\BusinessBrain\Actions;

class ExecutiveActionClassifier
{
    public function isExecutive(string $type): bool
    {
        return in_array(
            $type,
            [
                'receivable_recovery',
                'cash_management',
                'client_advocacy',
            ],
            true
        );
    }
}
