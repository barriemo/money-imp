<?php

namespace App\Domains\BusinessBrain\Organisation\Services;

use App\Domains\BusinessBrain\Organisation\BusinessProfile;

class BusinessProfileService
{
    public function current(): BusinessProfile
    {
        return new BusinessProfile(
            name: 'Purple Imp',

            industry: 'Digital agency',

            priorities: [
                'recover outstanding revenue',
                'improve project visibility',
                'protect client relationships',
                'increase profitability',
            ]
        );
    }
}