<?php

namespace App\Domains\CheerfulCharlie\Conflicts;

use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefContradictionService;
use App\Models\BusinessBelief;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CharlieConflictService
{
    public function __construct(
        private BusinessBeliefContradictionService $contradictions
    ) {}

    public function forSubject(
        Model $subject
    ): Collection {
        return BusinessBelief::query()
            ->where(
                'subject_type',
                $subject->getMorphClass()
            )
            ->where(
                'subject_id',
                $subject->getKey()
            )
            ->where(
                'status',
                'active'
            )
            ->get()
            ->filter(
                fn (BusinessBelief $belief) => $this->contradictions
                    ->hasConflict($belief)
            )
            ->map(
                function (
                    BusinessBelief $belief
                ): array {
                    $conflicts =
                        $this->contradictions
                            ->contradictions($belief);

                    return [
                        'belief' => $belief,

                        'current_value' => $belief->value,

                        'confidence' => $belief->confidence,

                        'contradictions' => $conflicts,

                        'message' => $this->message(
                            $belief,
                            $conflicts
                        ),
                    ];
                }
            )
            ->values();
    }

    private function message(
        BusinessBelief $belief,
        Collection $conflicts
    ): string {
        $latest =
            $conflicts->first();

        $observedValue =
            $latest?->metadata[
                'observed_value'
            ]
            ?? null;

        if ($observedValue) {
            return sprintf(
                'I currently believe %s is %s, but I have evidence suggesting %s.',
                str_replace(
                    '_',
                    ' ',
                    $belief->key
                ),
                $belief->value,
                $observedValue
            );
        }

        return sprintf(
            'I have conflicting evidence about %s.',
            str_replace(
                '_',
                ' ',
                $belief->key
            )
        );
    }
}
