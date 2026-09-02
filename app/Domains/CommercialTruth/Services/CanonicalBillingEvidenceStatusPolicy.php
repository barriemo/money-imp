<?php

namespace App\Domains\CommercialTruth\Services;

final class CanonicalBillingEvidenceStatusPolicy
{
    private const ADMISSIBLE_STATUSES = [
        'paid',
        'overdue',
    ];

    /**
     * @return list<string>
     */
    public function admissibleStatuses(): array
    {
        return self::ADMISSIBLE_STATUSES;
    }

    public function admits(
        ?string $status
    ): bool {
        return in_array(
            $status,
            self::ADMISSIBLE_STATUSES,
            true
        );
    }
}
