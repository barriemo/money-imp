<?php

namespace App\Domains\BusinessBrain\CreditTruth;

use Illuminate\Support\Collection;

class CreditTruth
{
    public function __construct(
        public Collection $facilities,

        public int $facilityCount,

        public int $verifiedFacilityCount,

        public float $reportedExposure,

        public float $verifiedExposure,

        public float $reportedAvailableCredit,

        public float $minimumPaymentsDue,

        public int $confidence
    ) {}
}
