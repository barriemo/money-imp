<?php

namespace App\Domains\WorkIntelligence\Services;

use App\Domains\WorkIntelligence\Analysis\BillabilityReasoner;
use App\Domains\WorkIntelligence\Evidence\WorkLogEvidenceService;
use App\Domains\WorkIntelligence\Splitting\WorkActivitySplitter;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ConversationalWorkLogService
{
    public function __construct(
        private BillabilityReasoner $reasoner,
        private WorkActivitySplitter $splitter,
        private WorkLogEvidenceService $evidence,
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

        $workLog = WorkLog::create([
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

        $this->evidence->create(
            $workLog
        );

        return $workLog;
    }

    public function createMany(
        Client $client,
        User $user,
        WorkObservationCollection $observations,
        Carbon $performedAt
    ): Collection {
        $assessment =
            $this->reasoner->assess(
                $observations
            );

        $activities =
            $this->splitter->split(
                $observations
            );

        $rate = 95.00;

        return $activities->items->map(
            function ($activity) use (
                $client,
                $user,
                $performedAt,
                $assessment,
                $rate
            ) {
                $workLog = WorkLog::create([
                    'client_id' => $client->id,

                    'user_id' => $user->id,

                    'description' => $activity->description,

                    'minutes' => $activity->minutes,

                    'performed_at' => $performedAt,

                    'billing_hint' => $assessment->billable
                        ? 'billable'
                        : 'unsure',

                    'commercial_status' => 'unreviewed',

                    'rate_snapshot' => $rate,

                    'commercial_value' => round(
                        ($activity->minutes / 60) * $rate,
                        2
                    ),

                    'commercial_notes' => $assessment->reason,
                ]);

                $this->evidence->create(
                    $workLog
                );

                return $workLog;
            }
        );
    }
}
