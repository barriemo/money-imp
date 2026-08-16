<?php

namespace App\Domains\BusinessBrain\CreditTruth;

use App\Models\CreditFacility;

class CreditTruthService
{
    public function current(): CreditTruth
    {
        $facilities =
            CreditFacility::query()
                ->where(
                    'status',
                    'active'
                )
                ->orderBy(
                    'provider'
                )
                ->get()
                ->map(
                    function (CreditFacility $facility): CreditFacilityTruth {
                        $balance =
                            $facility->reported_balance !== null
                                ? (float) $facility->reported_balance
                                : null;

                        $limit =
                            $facility->credit_limit !== null
                                ? (float) $facility->credit_limit
                                : null;

                        $available =
                            $limit !== null
                            && $balance !== null
                                ? round(
                                    max(
                                        0,
                                        $limit - $balance
                                    ),
                                    2
                                )
                                : null;

                        return new CreditFacilityTruth(
                            id: $facility->id,

                            provider: $facility->provider,

                            name: $facility->name,

                            type: $facility->facility_type,

                            creditLimit: $limit,

                            reportedBalance: $balance,

                            availableCredit: $available,

                            minimumPayment: $facility
                                ->minimum_payment !== null
                                    ? (float) $facility
                                        ->minimum_payment
                                    : null,

                            paymentDueAt: $facility
                                ->payment_due_at?->toDateString(),

                            verified: (bool) $facility
                                ->verified,

                            confidence: (int) $facility
                                ->confidence,

                            status: $facility
                                ->status,

                            balanceAt: $facility
                                ->reported_balance_at?->toIso8601String()
                        );
                    }
                )
                ->values();

        $verified =
            $facilities
                ->where(
                    'verified',
                    true
                );

        $facilityCount =
            $facilities->count();

        $verifiedFacilityCount =
            $verified->count();

        return new CreditTruth(
            facilities: $facilities,

            facilityCount: $facilityCount,

            verifiedFacilityCount: $verifiedFacilityCount,

            reportedExposure: round(
                (float) $facilities
                    ->sum(
                        fn (CreditFacilityTruth $facility) => $facility
                            ->reportedBalance
                            ?? 0
                    ),
                2
            ),

            verifiedExposure: round(
                (float) $verified
                    ->sum(
                        fn (CreditFacilityTruth $facility) => $facility
                            ->reportedBalance
                            ?? 0
                    ),
                2
            ),

            reportedAvailableCredit: round(
                (float) $facilities
                    ->sum(
                        fn (CreditFacilityTruth $facility) => $facility
                            ->availableCredit
                            ?? 0
                    ),
                2
            ),

            minimumPaymentsDue: round(
                (float) $facilities
                    ->sum(
                        fn (CreditFacilityTruth $facility) => $facility
                            ->minimumPayment
                            ?? 0
                    ),
                2
            ),

            confidence: $facilityCount > 0
                ? (int) round(
                    (
                        $verifiedFacilityCount
                        / $facilityCount
                    ) * 100
                )
                : 0
        );
    }
}
