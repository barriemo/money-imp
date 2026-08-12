<?php

namespace App\Domains\WorkIntelligence\Evidence;

use App\Domains\Evidence\EvidenceItem;
use App\Domains\Evidence\EvidenceRepository;
use App\Models\WorkLog;

class WorkLogEvidenceService
{
    public function __construct(
        private EvidenceRepository $repository
    ) {}

    public function create(
        WorkLog $workLog
    ): EvidenceItem {
        $evidence =
            new EvidenceItem(
                type: 'work_log',

                source: 'staff',

                summary: $workLog->description,

                confidence: 80,

                subject: $workLog,

                metadata: [
                    'work_log_id' => $workLog->id,

                    'client_id' => $workLog->client_id,

                    'minutes' => $workLog->minutes,

                    'commercial_value' => $workLog->commercial_value,

                    'billing_hint' => $workLog->billing_hint,

                    'commercial_status' => $workLog->commercial_status,
                ]
            );

        $this->repository->add(
            $evidence
        );

        return $evidence;
    }
}
