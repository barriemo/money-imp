<?php

namespace App\Domains\EvidenceAcquisition\Providers;

use App\Domains\EvidenceAcquisition\Contracts\EvidenceQuestionProvider;
use App\Domains\EvidenceAcquisition\EvidenceQuestion;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Illuminate\Support\Collection;

class FinancialEvidenceProvider implements EvidenceQuestionProvider
{
    public function __construct(
        private FinancialTruthService $truth
    ) {}

    public function questions(): Collection
    {
        $truth =
            $this->truth
                ->build();

        $questions = collect();

        if (
            ($truth['cash']['confidence'] ?? 0)
            < 100
        ) {
            $questions->push(
                new EvidenceQuestion(
                    question: 'What are the current bank and card balances?',

                    reason: 'Charlie cannot establish a verified cash position.',

                    priority: 100,

                    domain: 'financial_truth',

                    evidence: [
                        'cash_confidence' => $truth['cash']['confidence'] ?? 0,
                    ]
                )
            );
        }

        if (
            ($truth['receivables']['confidence'] ?? 0)
            < 100
        ) {
            $questions->push(
                new EvidenceQuestion(
                    question: 'Which outstanding invoices are genuinely collectible?',

                    reason: 'Ledger receivables are not yet verified as cash.',

                    priority: 80,

                    domain: 'financial_truth'
                )
            );
        }

        return $questions;
    }
}
