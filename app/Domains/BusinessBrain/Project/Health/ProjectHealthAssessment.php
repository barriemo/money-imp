<?php

namespace App\Domains\BusinessBrain\Project\Health;

final readonly class ProjectHealthAssessment
{
    public function __construct(
        public string $status,

        public array $reasons = [],

        public array $signals = [],

        public ?string $recommendedAction = null
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function requiresAttention(): bool
    {
        return in_array(
            $this->status,
            [
                'blocked',
                'at_risk',
            ],
            true
        );
    }
}
