<?php

namespace App\Domains\WorkIntelligence\Services;

use App\Domains\WorkIntelligence\Analysis\BillabilityReasoner;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;

class ConversationalWorkLogService
{
    public function __construct(
        private BillabilityReasoner $reasoner,
    ) {}

    public function create(
        Client $client,
        User $user,
        WorkObservationCollection $observations,
        int $minutes,
        Carbon $performedAt
    ): WorkLog {
        $assessment =
            $this->reasoner->assess(
                $observations
            );

        $rate = 95.00;

        return WorkLog::create([
            'client_id' => $client->id,

            'user_id' => $user->id,

            'description' => (string) (
                $observations
                    ->items
                    ->firstWhere(
                        'type',
                        'work_described'
                    )
                    ?->value
                ?? 'Conversation captured work'
            ),

            'minutes' => $minutes,

            'performed_at' => $performedAt,

            'billing_hint' => $assessment->billable
                ? 'billable'
                : 'unsure',

            'commercial_status' => 'unreviewed',

            'rate_snapshot' => $rate,

            'commercial_value' => round(
                ($minutes / 60) * $rate,
                2
            ),

            'commercial_notes' => $assessment->reason,
        ]);
    }
}
