<?php

namespace App\Domains\BusinessBrain\MorningBrief\Presenters;

use App\Domains\BusinessBrain\MorningBrief\MorningBrief;

class MorningBriefPresenter
{
    public function present(
        MorningBrief $brief
    ): array {
        return [
            'signal_count' => $brief->signals->count(),

            'signals' => $brief->signals
                ->map(
                    function ($signal) {
                        return [
                            'type' => $signal->type,

                            'client' => $signal->client,

                            'priority' => $signal->priority,

                            'value' => $signal->value,

                            'reason' => $signal->reason,
                        ];
                    }
                )
                ->values()
                ->all(),
        ];
    }
}
