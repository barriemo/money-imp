<?php

namespace App\Domains\BusinessBrain\MorningBrief\History;

use Illuminate\Support\Collection;

class MorningBriefChangeDetector
{
    public function detect(
        MorningBriefSnapshot $previous,

        MorningBriefSnapshot $current
    ): Collection {
        $previousSignals =
            $previous->signals->keyBy(
                fn ($signal) => $signal->type
            );

        $currentSignals =
            $current->signals->keyBy(
                fn ($signal) => $signal->type
            );

        $types =
            $previousSignals
                ->keys()
                ->merge(
                    $currentSignals->keys()
                )
                ->unique();

        return $types
            ->map(
                function (string $type) use (
                    $previousSignals,
                    $currentSignals
                ) {
                    $previousSignal =
                        $previousSignals->get(
                            $type
                        );

                    $currentSignal =
                        $currentSignals->get(
                            $type
                        );

                    if (
                        ! $previousSignal
                        && $currentSignal
                    ) {
                        return new MorningBriefChange(
                            type: 'new',

                            signalType: $type,

                            previousValue: 0,

                            currentValue: (float) $currentSignal->value,

                            difference: (float) $currentSignal->value
                        );
                    }

                    if (
                        $previousSignal
                        && ! $currentSignal
                    ) {
                        return new MorningBriefChange(
                            type: 'resolved',

                            signalType: $type,

                            previousValue: (float) $previousSignal->value,

                            currentValue: 0,

                            difference: -((float) $previousSignal->value)
                        );
                    }

                    $difference =
                        (float) $currentSignal->value
                        - (float) $previousSignal->value;

                    if ($difference === 0.0) {
                        return null;
                    }

                    return new MorningBriefChange(
                        type: $difference > 0
                            ? 'worsened'
                            : 'improved',

                        signalType: $type,

                        previousValue: (float) $previousSignal->value,

                        currentValue: (float) $currentSignal->value,

                        difference: $difference
                    );
                }
            )
            ->filter()
            ->values();
    }
}
