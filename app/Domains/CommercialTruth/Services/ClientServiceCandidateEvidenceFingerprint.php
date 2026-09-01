<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidate;

final class ClientServiceCandidateEvidenceFingerprint
{
    public function forCandidate(
        ClientServiceCandidate $candidate
    ): string {
        $ids = $candidate->invoiceItemIds;

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
