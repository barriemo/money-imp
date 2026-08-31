<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

use App\Models\BankTransaction;
use Illuminate\Support\Collection;

class StatutoryPaymentEvidenceService
{
    /**
     * @return Collection<int, StatutoryPaymentEvidence>
     */
    public function current(): Collection
    {
        return $this->classify(
            BankTransaction::query()
                ->where('amount', '<', 0)
                ->get()
        );
    }

    /**
     * @param  Collection<int, BankTransaction>  $transactions
     * @return Collection<int, StatutoryPaymentEvidence>
     */
    public function classify(Collection $transactions): Collection
    {
        return $transactions
            ->map(
                fn (BankTransaction $transaction) => $this->evidence($transaction)
            )
            ->filter()
            ->values();
    }

    private function evidence(
        BankTransaction $transaction
    ): ?StatutoryPaymentEvidence {
        $description = $this->normalise(
            (string) $transaction->description
        );

        $rawExplanations = collect(
            $transaction->raw_payload[
                'bank_transaction_explanations'
            ] ?? []
        );

        $explicitVatFromBank =
            preg_match(
                '/\bHMRC\s+VAT\b/i',
                $description
            ) === 1;

        $explicitVatFromExplanation =
            $rawExplanations->contains(
                function (array $explanation): bool {
                    $description = $this->normalise(
                        (string) (
                            $explanation['description']
                            ?? ''
                        )
                    );

                    $detail = $this->normalise(
                        (string) (
                            $explanation['detail']
                            ?? ''
                        )
                    );

                    return preg_match(
                        '/\bHMRC\s+VAT\b/i',
                        $description
                    ) === 1
                        && preg_match(
                            '/\bVAT\b/i',
                            $detail
                        ) === 1;
                }
            );

        if (
            $explicitVatFromBank
            || $explicitVatFromExplanation
        ) {
            $signals = [];

            if ($explicitVatFromBank) {
                $signals[] =
                    'bank_description_explicit_hmrc_vat';
            }

            if ($explicitVatFromExplanation) {
                $signals[] =
                    'freeagent_explanation_explicit_vat';
            }

            return new StatutoryPaymentEvidence(
                bankTransactionId: (string) $transaction->id,

                date: $transaction
                    ->transaction_date
                    ?->toDateString()
                    ?? '',

                amount: abs((float) $transaction->amount),

                authority: 'hmrc',

                taxType: 'vat',

                classification: 'explicit_tax_type',

                confidence: 95,

                description: (string) $transaction->description,

                signals: $signals,
            );
        }

        /*
         * Do not treat generic words such as "tax",
         * "VAT NON APPLICA", or "TAXIS" as HMRC
         * evidence. HMRC itself must be present.
         */
        if (
            preg_match(
                '/\bHMRC\b/i',
                $description
            ) !== 1
        ) {
            return null;
        }

        return new StatutoryPaymentEvidence(
            bankTransactionId: (string) $transaction->id,

            date: $transaction
                ->transaction_date
                ?->toDateString()
                ?? '',

            amount: abs((float) $transaction->amount),

            authority: 'hmrc',

            taxType: null,

            classification: 'authority_only',

            confidence: 90,

            description: (string) $transaction->description,

            signals: [
                'bank_description_explicit_hmrc',
                'tax_type_unresolved',
            ],
        );
    }

    private function normalise(
        string $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $value
            ) ?? ''
        );
    }
}
