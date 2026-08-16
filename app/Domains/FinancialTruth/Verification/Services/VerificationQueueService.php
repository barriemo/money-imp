<?php

namespace App\Domains\FinancialTruth\Verification\Services;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use App\Domains\FinancialTruth\Verification\DTOs\VerificationCandidate;
use Illuminate\Support\Collection;

class VerificationQueueService
{
    public function __construct(
        private FinancialTruthService $financialTruth
    ) {}

    public function current(): Collection
    {
        $truth =
            $this->financialTruth
                ->build();

        return collect(
            $truth['accounts']
        )
            ->filter(
                fn (array $account): bool => ! (
                    $account['verified']
                    ?? false
                )
            )
            ->filter(
                fn (array $account): bool => (
                    $account['reported_balance']
                    ?? null
                ) !== null
            )
            ->map(
                fn (array $account) => $this->candidate(
                    $account
                )
            )
            ->sortByDesc(
                fn (VerificationCandidate $candidate) => [
                    $candidate->priority,
                    abs(
                        $candidate->amount
                        ?? 0
                    ),
                ]
            )
            ->values();
    }

    public function bestNext(): ?VerificationCandidate
    {
        return $this->current()
            ->first();
    }

    private function candidate(
        array $account
    ): VerificationCandidate {
        $balance =
            (float) (
                $account['reported_balance']
                ?? 0
            );

        $isCard =
            (
                $account['type']
                ?? null
            ) === 'CreditCardAccount';

        $amount =
            $isCard
                ? abs(
                    $balance
                )
                : $balance;

        return new VerificationCandidate(
            key: 'bank-account-'.$account['id'],

            type: $isCard
                ? 'credit_card_balance'
                : 'bank_balance',

            subject: (string) $account['name'],

            amount: $amount,

            source: (string) (
                $account['source']
                ?? 'unknown'
            ),

            confidence: (int) (
                $account['confidence']
                ?? 0
            ),

            priority: $this->priority(
                $account,
                $amount
            ),

            reason: $this->reason(
                $account,
                $amount
            ),

            recommendedAction: $isCard
                ? 'Provide current card statement or trusted card balance evidence.'
                : 'Provide current bank statement, bank balance export, or open banking evidence.'
        );
    }

    private function priority(
        array $account,
        float $amount
    ): int {
        $isBank =
            (
                $account['type']
                ?? null
            ) === 'StandardBankAccount';

        $score = 0;

        if ($isBank) {
            $score += 50;
        } else {
            $score += 30;
        }

        $score += match (true) {
            $amount >= 100000 => 40,
            $amount >= 25000 => 30,
            $amount >= 5000 => 20,
            $amount > 0 => 10,
            default => 0,
        };

        $confidence =
            (int) (
                $account['confidence']
                ?? 0
            );

        if ($confidence < 50) {
            $score += 10;
        }

        return min(
            100,
            $score
        );
    }

    private function reason(
        array $account,
        float $amount
    ): string {
        $isCard =
            (
                $account['type']
                ?? null
            ) === 'CreditCardAccount';

        if ($isCard) {
            return sprintf(
                'Reported credit-card exposure of £%s is not verified and may affect safe available cash.',
                number_format(
                    $amount,
                    2
                )
            );
        }

        return sprintf(
            'Reported cash balance of £%s is not verified and therefore cannot yet be included in Financial Truth.',
            number_format(
                $amount,
                2
            )
        );
    }
}
