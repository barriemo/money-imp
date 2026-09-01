<?php

namespace App\Domains\BusinessBrain\Cfo\Briefing;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\Executive\Contracts\ExecutiveBrief;
use App\Domains\BusinessBrain\Executive\Contracts\ExecutiveBriefService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Domains\FinancialTruth\Verification\Services\VerificationQueueService;

class CfoBriefService implements ExecutiveBriefService
{
    public function __construct(
        private FinancialPositionService $financialPosition,

        private BusinessBrainBriefService $businessBrain,

        private VerificationQueueService $verificationQueue,
        private CurrentCommercialPositionService $commercialPosition
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

        $commercialPosition =
            $this->commercialPosition
                ->position(
                    $position->asOf
                );

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

            asOf: $position->asOf,
            commercialPosition: $commercialPosition
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

        $reconciliation =
            $position->liabilities->reconciliation;

        if (
            ($reconciliation['payments_observed'] ?? 0) > 0
            &&
            ($reconciliation['unresolved_difference'] ?? 0) > 0
        ) {
            $risks[] =
                sprintf(
                    'Observed statutory payments of £%s exist, but £%s remains unresolved against reported obligations.',
                    number_format(
                        $reconciliation['payments_observed'],
                        2
                    ),
                    number_format(
                        $reconciliation['unresolved_difference'],
                        2
                    )
                );
        }

        if ($position->liabilities->reportedOverdue > 0) {
            $risks[] =
                sprintf(
                    'Source evidence reports £%s of current overdue liabilities whose settlement is not yet established.',
                    number_format(
                        $position->liabilities->reportedOverdue,
                        2
                    )
                );
        }

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

        $reconciliation =
            $position->liabilities->reconciliation;

        if (
            ($reconciliation['unresolved_difference'] ?? 0) > 0
        ) {
            $unknowns[] =
                sprintf(
                    '£%s of reported statutory obligations remain unresolved after observed settlement evidence.',
                    number_format(
                        $reconciliation['unresolved_difference'],
                        2
                    )
                );
        }

        if ($position->liabilities->settlementUnresolved > 0) {
            if (! $position->liabilities->canInferPaymentAbsence) {
                $unknowns[] =
                    sprintf(
                        'Settlement of £%s of reported overdue liabilities is unresolved because bank transaction evidence is not current enough to infer absence of payment.',
                        number_format(
                            $position->liabilities->settlementUnresolved,
                            2
                        )
                    );
            } else {
                $unknowns[] =
                    sprintf(
                        'Settlement of £%s of reported overdue liabilities remains unresolved pending payment-to-obligation reconciliation.',
                        number_format(
                            $position->liabilities->settlementUnresolved,
                            2
                        )
                    );
            }
        }

        if (
            $position->liabilities
                ->historicalReportedUnresolved > 0
        ) {
            $unknowns[] =
                sprintf(
                    '£%s of older reported liability evidence remains historically unresolved and is excluded from current reported exposure.',
                    number_format(
                        $position->liabilities
                            ->historicalReportedUnresolved,
                        2
                    )
                );
        }

        foreach (
            $position->liabilities->unknownCategories as $category
        ) {
            $label = match ($category) {
                'vat' => 'VAT',
                'paye' => 'PAYE',
                'corporation_tax' => 'Corporation tax',
                default => ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $category
                    )
                ),
            };

            $unknowns[] =
                $label
                .' liability evidence is currently insufficient.';
        }

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

        $reconciliation =
            $position->liabilities->reconciliation;

        if (
            ($reconciliation['unresolved_difference'] ?? 0) > 0
        ) {
            $priorities[] =
                'Reconcile observed statutory payments against reported obligations to establish the true outstanding position.';
        }

        if (
            $position->liabilities->reportedOverdue > 0
            && ! $position->liabilities
                ->bankTransactionEvidenceCurrent
        ) {
            $priorities[] =
                'Refresh bank transaction evidence before deciding whether reported overdue statutory liabilities remain unpaid.';
        }

        if (
            $position->liabilities
                ->unknownCategories !== []
        ) {
            $priorities[] =
                'Establish missing statutory liability evidence for PAYE and corporation tax.';
        }

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

        if ($position->liabilities->reportedOverdue > 0) {
            $questions[] =
                'Which reported overdue statutory liabilities have actually been settled?';
        }

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
