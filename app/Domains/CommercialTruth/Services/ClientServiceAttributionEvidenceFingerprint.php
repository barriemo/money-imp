<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceAttributionCandidate;

final class ClientServiceAttributionEvidenceFingerprint
{
    public function forCandidate(
        ClientServiceAttributionCandidate $candidate
    ): string {
        $ids =
            $candidate->invoiceItemIds;

        sort(
            $ids,
            SORT_STRING
        );

        return hash(
            'sha256',
            implode(
                '|',
                $ids
            )
        );
    }
}
