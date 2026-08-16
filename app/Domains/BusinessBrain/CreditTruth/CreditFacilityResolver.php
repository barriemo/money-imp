<?php

namespace App\Domains\BusinessBrain\CreditTruth;

use App\Models\CreditFacility;

class CreditFacilityResolver
{
    public function forProvider(
        string $provider
    ): CreditFacility {
        return match ($provider) {
            'capital_on_tap_pdf' => CreditFacility::firstOrCreate(
                [
                    'provider' => 'capital_on_tap',
                ],
                [
                    'name' => 'Capital on Tap',
                    'facility_type' => 'business_credit_card',
                    'currency' => 'GBP',
                    'status' => 'active',
                    'confidence' => 0,
                    'verified' => false,
                ]
            ),

            default => throw new \InvalidArgumentException(
                'Unsupported credit statement provider: '.$provider
            ),
        };
    }
}
