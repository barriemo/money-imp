<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\ProjectAction;

class ProjectActionPrioritiser
{
    public function prioritise(ProjectAction $action): array
    {
        $score = 0;
        $drivers = [];

        if ($action->priority === 'critical') {
            $score += 40;
            $drivers[] = 'Critical priority action';
        }

        if ($action->priority === 'high') {
            $score += 40;
            $drivers[] = 'High priority action';
        }

        if ($action->evidence->max('confidence') >= 80) {
            $score += 20;
            $drivers[] = 'Strong evidence confidence';
        }

        if ($action->project?->commercial_value >= 100000) {
            $score += 20;
            $drivers[] = 'High commercial value';
        }

        return [
            'score' => $score,
            'category' => $this->category($score),
            'reason' => $this->reason($score),
            'drivers' => $drivers,
        ];
    }

    protected function category(int $score): string
    {
        return match (true) {
            $score >= 80 => 'urgent',
            $score >= 50 => 'important',
            default => 'normal',
        };
    }

    protected function reason(int $score): string
    {
        return match (true) {
            $score >= 80 => 'High business impact requires attention.',
            $score >= 50 => 'Action has meaningful business importance.',
            default => 'Action should be monitored.',
        };
    }
}
