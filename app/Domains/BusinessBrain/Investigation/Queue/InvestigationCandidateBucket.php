<?php

namespace App\Domains\BusinessBrain\Investigation\Queue;

enum InvestigationCandidateBucket: string
{
    case ReadyNow = 'ready_now';

    case WaitingForEvidence = 'waiting_for_evidence';

    case LowerPriority = 'lower_priority';
}
