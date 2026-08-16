<?php

namespace App\Domains\BusinessBrain\Cfo\Briefing;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBrief;
use App\Domains\BusinessBrain\Executive\Contracts\ExecutiveBrief;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\FinancialTruth\Verification\DTOs\VerificationCandidate;
use Carbon\CarbonImmutable;

class CfoBrief implements ExecutiveBrief
{
    public function __construct(
        public FinancialPosition $financialPosition,

        public BusinessBrainBrief $businessBrain,

        public string $overallStatus,

        public int $overallConfidence,

        public array $strengths,

        public array $risks,

        public array $unknowns,

        public array $priorities,

        public array $recommendations,

        public array $questions,

        public ?VerificationCandidate $bestNextVerification,

        public CarbonImmutable $asOf
    ) {}

    public function confidence(): int
    {
        return $this->overallConfidence;
    }

    public function status(): string
    {
        return $this->overallStatus;
    }
}
