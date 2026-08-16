<?php

namespace App\Domains\BusinessBrain\Cfo\Briefing;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\Executive\Contracts\ExecutiveBrief;
use App\Domains\BusinessBrain\Executive\Contracts\ExecutiveBriefService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\FinancialTruth\Verification\Services\VerificationQueueService;

class CfoBriefService implements ExecutiveBriefService
{
    public function __construct(
        private FinancialPositionService $financialPosition,

        private BusinessBrainBriefService $businessBrain,

        private VerificationQueueService $verificationQueue
    ) {}

    public function current(): ExecutiveBrief
    {
        $position =
            $this->financialPosition
                ->current();

        $brain =
            $this->businessBrain
                ->current();

        $bestNextVerification =
            $this->verificationQueue
                ->bestNext();

        return new CfoBrief(
            financialPosition: $position,

            businessBrain: $brain,

            overallStatus: $this->status(
                $position
            ),

            overallConfidence: $position->confidence,

            strengths: $this->strengths(
                $position
            ),

            risks: $this->risks(
                $position,
                $brain
            ),

            unknowns: $this->unknowns(
                $position
            ),

            priorities: $this->priorities(
                $position,
                $brain
            ),

            recommendations: $this->recommendations(
                $position,
                $brain
            ),

            questions: $this->questions(
                $position
            ),

            bestNextVerification: $bestNextVerification,

            asOf: $position->asOf
        );
    }

    private function status(
        FinancialPosition $position
    ): string {
        if ($position->confidence < 40) {
            return 'uncertain';
        }

        if (
            ! $position->liabilities->coverageComplete
            || $position->cash->safeAvailableCash === null
            || $position->receivables->verifiedCollectible === null
        ) {
            return 'cautious';
        }

        return 'established';
    }

    private function strengths(
        FinancialPosition $position
    ): array {
        $strengths = [];

        if ($position->cash->verifiedCash > 0) {
            $strengths[] =
                sprintf(
                    'Verified cash evidence currently totals £%s.',
                    number_format(
                        $position->cash->verifiedCash,
                        2
                    )
                );
        }

        if ($position->credit->confidence >= 80) {
            $strengths[] =
                'Credit exposure is supported by high-confidence evidence.';
        }

        return $strengths;
    }

    private function risks(
        FinancialPosition $position,
        $brain
    ): array {
        $risks = [];

        if ($position->receivables->ledgerOutstanding > 0) {
            $risks[] =
                sprintf(
                    'The accounting ledger reports £%s of outstanding receivables.',
                    number_format(
                        $position->receivables->ledgerOutstanding,
                        2
                    )
                );
        }

        if ($position->credit->verifiedExposure > 0) {
            $risks[] =
                sprintf(
                    'Verified credit exposure is £%s.',
                    number_format(
                        $position->credit->verifiedExposure,
                        2
                    )
                );
        }

        if ($brain->activeInvestigationCount > 0) {
            $risks[] =
                sprintf(
                    '%d active investigation%s may affect current business truth.',
                    $brain->activeInvestigationCount,
                    $brain->activeInvestigationCount === 1
                        ? ''
                        : 's'
                );
        }

        return $risks;
    }

    private function unknowns(
        FinancialPosition $position
    ): array {
        $unknowns = [];

        if (! $position->liabilities->coverageComplete) {
            $unknowns[] =
                'Liability coverage is incomplete. Known liabilities must not be treated as total liabilities.';
        }

        if ($position->receivables->verifiedCollectible === null) {
            $unknowns[] =
                'Collectible receivables have not yet been verified.';
        }

        if ($position->cash->safeAvailableCash === null) {
            $unknowns[] =
                'Safe available cash cannot yet be established.';
        }

        return $unknowns;
    }

    private function priorities(
        FinancialPosition $position,
        $brain
    ): array {
        $priorities = [];

        if (! $position->liabilities->coverageComplete) {
            $priorities[] =
                'Verify outstanding liabilities and statutory obligations.';
        }

        if ($position->receivables->verifiedCollectible === null) {
            $priorities[] =
                'Establish which ledger receivables are genuinely collectible.';
        }

        if ($brain->bestNextCandidate) {
            $priorities[] =
                sprintf(
                    'Investigate %s: %s',
                    $brain->bestNextCandidate->subjectName,
                    $brain->bestNextCandidate->question
                );
        }

        return $priorities;
    }

    private function recommendations(
        FinancialPosition $position,
        $brain
    ): array {
        $recommendations = [];

        if ($position->confidence < 40) {
            $recommendations[] =
                'Do not rely on the headline financial position for a material decision until the weakest evidence gaps are resolved.';
        }

        if ($position->cash->safeAvailableCash === null) {
            $recommendations[] =
                'Establish safe available cash before making discretionary spending or investment decisions.';
        }

        if ($brain->waitingInvestigationCount > 0) {
            $recommendations[] =
                'Resolve evidence required by waiting investigations before treating their conclusions as settled.';
        }

        return $recommendations;
    }

    private function questions(
        FinancialPosition $position
    ): array {
        $questions = [];

        if (! $position->liabilities->coverageComplete) {
            $questions[] =
                'Which liabilities remain unverified?';
        }

        if ($position->receivables->verifiedCollectible === null) {
            $questions[] =
                'Which outstanding invoices are genuinely collectible?';
        }

        if ($position->cash->safeAvailableCash === null) {
            $questions[] =
                'What cash is genuinely safe to spend?';
        }

        return $questions;
    }
}
