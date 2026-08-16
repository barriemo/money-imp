<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Investigation;

use App\Domains\BusinessBrain\Investigation\Claims\HypothesisClaim;
use App\Domains\BusinessBrain\Investigation\Claims\HypothesisClaimSet;
use App\Domains\BusinessBrain\Investigation\Hypothesis;

class PaymentHypothesisClaimBuilder
{
    public function build(
        Hypothesis $hypothesis
    ): HypothesisClaimSet {
        $claims = [
            new HypothesisClaim(
                key: 'payment_occurred',
                statement: 'The relevant invoices were paid.'
            ),

            new HypothesisClaim(
                key: 'payment_received',
                statement: 'The business received the payments.'
            ),
        ];

        if (
            str_contains(
                strtolower(
                    $hypothesis->statement
                ),
                'hsbc'
            )
        ) {
            $claims[] =
                new HypothesisClaim(
                    key: 'payment_destination_hsbc',
                    statement: 'The payments were received into the HSBC account.'
                );
        }

        return new HypothesisClaimSet(
            claims: $claims
        );
    }
}
