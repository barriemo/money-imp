<?php

namespace App\Domains\BusinessBrain\Investigation\Timeline;

use App\Models\InvestigationCase;

class InvestigationTimelinePresenter
{
    public function __construct(
        private InvestigationEpisodeBuilder $episodes
    ) {}

    public function present(
        InvestigationCase $case
    ): string {
        $case->loadMissing(
            'events'
        );

        $lines = [
            sprintf(
                '%s — %s',
                $case->subject_name
                    ?? 'Investigation',
                strtoupper(
                    $case->status
                )
            ),
            '',
            sprintf(
                'Case: %s',
                $case->title
            ),
        ];

        if ($case->current_hypothesis) {
            $lines[] =
                sprintf(
                    'Current hypothesis: %s',
                    $case->current_hypothesis
                );
        }

        $lines[] =
            sprintf(
                'Confidence: %d%%',
                $case->confidence
            );

        if ($case->verdict) {
            $lines[] =
                sprintf(
                    'Verdict: %s',
                    $case->verdict
                );
        }

        $lines[] = '';
        $lines[] = 'Investigation history';

        $episodes =
            $this->episodes
                ->build(
                    $case
                );

        foreach ($episodes as $index => $episode) {
            $lines[] = '';

            $lines[] =
                $episode->legacy
                    ? 'Initial / historical investigation'
                    : sprintf(
                        'Evidence episode %d',
                        $index + 1
                    );

            if ($episode->trigger) {
                $lines[] =
                    sprintf(
                        'Trigger: %s',
                        $episode->trigger
                    );
            }

            $retractions =
                $episode->events
                    ->where(
                        'type',
                        'hypothesis_retracted'
                    )
                    ->values();

            if ($retractions->isNotEmpty()) {
                $lines[] = 'Corrections';

                foreach ($retractions as $event) {
                    $lines[] =
                        sprintf(
                            '- Retracted: %s',
                            $event->description
                        );

                    $reason =
                        $event->payload['reason']
                        ?? null;

                    if ($reason) {
                        $lines[] =
                            sprintf(
                                '  Reason: %s',
                                $reason
                            );
                    }

                    $replacement =
                        $event->payload['replacement']
                        ?? null;

                    if ($replacement) {
                        $lines[] =
                            sprintf(
                                '  Replaced with: %s',
                                $replacement
                            );
                    }
                }
            }

            if ($episode->hypothesisChanges->isNotEmpty()) {
                $lines[] = 'Hypothesis';

                foreach (
                    $episode->hypothesisChanges as $event
                ) {
                    $lines[] =
                        '- '.$this->eventSummary(
                            $event
                        );
                }
            }

            if ($episode->claimChanges->isNotEmpty()) {
                $lines[] = 'Claims';

                foreach (
                    $episode->claimChanges as $event
                ) {
                    $lines[] =
                        '- '.$this->eventSummary(
                            $event
                        );
                }
            }

            if ($episode->outcome) {
                $lines[] =
                    sprintf(
                        'Outcome: %s',
                        $episode->outcome
                    );
            }

            if (
                $episode->legacy
                && $episode->hypothesisChanges->isEmpty()
                && $episode->claimChanges->isEmpty()
            ) {
                foreach (
                    $episode->events as $event
                ) {
                    $lines[] =
                        sprintf(
                            '- %s — %s',
                            $this->label(
                                $event->type
                            ),
                            $event->description
                        );
                }
            }
        }

        return implode(
            PHP_EOL,
            $lines
        );
    }

    private function eventSummary(
        $event
    ): string {
        $payload =
            $event->payload
            ?? [];

        if (
            in_array(
                $event->type,
                [
                    'claim_changed',
                    'hypothesis_changed',
                ],
                true
            )
        ) {
            $statement =
                $payload['statement']
                ?? $payload['hypothesis']
                ?? preg_replace(
                    '/\\s+—\\s+[a-z_ -]+\\s+\\(\\d+%\\)\\s+→\\s+[a-z_ -]+\\s+\\(\\d+%\\)$/i',
                    '',
                    $event->description
                );

            return sprintf(
                '%s — %s (%d%%) → %s (%d%%)',
                rtrim(
                    $statement,
                    '.'
                ),
                strtoupper(
                    (string) (
                        $payload['previous_status']
                        ?? 'unknown'
                    )
                ),
                (int) (
                    $payload['previous_confidence']
                    ?? 0
                ),
                strtoupper(
                    (string) (
                        $payload['status']
                        ?? 'unknown'
                    )
                ),
                (int) (
                    $payload['confidence']
                    ?? 0
                )
            );
        }

        if (
            in_array(
                $event->type,
                [
                    'claim_assessed',
                    'hypothesis_assessed',
                ],
                true
            )
        ) {
            $statement =
                $payload['statement']
                ?? $payload['hypothesis']
                ?? preg_replace(
                    '/\\s+—\\s+[a-z_ -]+\\s+\\(\\d+%\\)$/i',
                    '',
                    $event->description
                );

            return sprintf(
                '%s — %s (%d%%)',
                $statement,
                strtoupper(
                    (string) (
                        $payload['status']
                        ?? 'unknown'
                    )
                ),
                (int) (
                    $payload['confidence']
                    ?? 0
                )
            );
        }

        return $event->description;
    }

    private function label(
        string $type
    ): string {
        return match ($type) {
            'case_opened' => 'Case opened',

            'hypothesis_asserted' => 'Hypothesis recorded',

            'hypothesis_assessed' => 'Hypothesis assessed',

            'hypothesis_changed' => 'Hypothesis changed',

            'claim_assessed' => 'Claim assessed',

            'claim_changed' => 'Claim changed',

            'evidence_changed' => 'Evidence changed',

            'case_closed' => 'Case closed',

            default => ucfirst(
                str_replace(
                    '_',
                    ' ',
                    $type
                )
            ),
        };
    }
}
