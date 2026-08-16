<?php

namespace App\Domains\BusinessBrain\Project\Services;

use App\Models\ProjectActionOutcome;

class ProjectActionOutcomeEvaluator
{
    public function evaluate(ProjectActionOutcome $outcome): array
    {
        if ($outcome->confidence < 50) {
            return [
                'result' => 'uncertain',
                'confidence' => $outcome->confidence,
                'summary' => 'Outcome confidence is too low for a reliable assessment.',
            ];
        }

        if ($this->isPositiveValue($outcome->value)) {
            return [
                'result' => 'success',
                'confidence' => $outcome->confidence,
                'summary' => 'Outcome indicates a positive improvement.',
            ];
        }

        return [
            'result' => 'unknown',
            'confidence' => $outcome->confidence,
            'summary' => 'Outcome requires further evaluation.',
        ];
    }

    protected function isPositiveValue(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return str_starts_with($value, '+');
    }
}
