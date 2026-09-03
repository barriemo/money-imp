<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\BusinessBrain\Investigation\EvidenceBus\EvidenceChange;
use App\Domains\BusinessBrain\Investigation\EvidenceBus\InvestigationEvidenceBus;
use Illuminate\Support\Collection;

final class ReconciliationEvidencePublisher
{
    public function __construct(
        private readonly InvestigationEvidenceBus $evidenceBus,
    ) {}

    public function publish(
        string $type,
        ?string $clientId = null,
        array $metadata = []
    ): Collection {
        return $this->evidenceBus
            ->publish(
                new EvidenceChange(
                    domain: 'bank',

                    type: $type,

                    subjectType: $clientId !== null
                        ? 'client'
                        : null,

                    subjectId: $clientId,

                    metadata: $metadata
                )
            );
    }
}
