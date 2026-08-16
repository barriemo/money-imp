<?php

namespace App\Domains\BusinessBrain\CreditTruth;

use App\Models\CreditFacility;
use App\Models\CreditStatementEvidence;

class CreditStatementEvidenceService
{
    public function record(
        CreditFacility $facility,
        array $evidence
    ): CreditStatementEvidence {
        $statement =
            CreditStatementEvidence::updateOrCreate(
                [
                    'credit_facility_id' => $facility->id,

                    'import_batch_id' => $evidence[
                        'import_batch_id'
                    ] ?? null,
                ],
                [
                    'statement_from' => $evidence[
                        'statement_from'
                    ] ?? null,

                    'statement_to' => $evidence[
                        'statement_to'
                    ] ?? null,

                    'opening_balance' => $evidence[
                        'opening_balance'
                    ] ?? null,

                    'closing_balance' => $evidence[
                        'closing_balance'
                    ],

                    'minimum_payment' => $evidence[
                        'minimum_payment'
                    ] ?? null,

                    'payment_due_at' => $evidence[
                        'payment_due_at'
                    ] ?? null,

                    'credit_limit' => $evidence[
                        'credit_limit'
                    ] ?? null,

                    'source' => $evidence[
                        'source'
                    ],

                    'verified' => $evidence[
                        'verified'
                    ] ?? false,

                    'confidence' => $evidence[
                        'confidence'
                    ] ?? 0,

                    'metadata' => $evidence[
                        'metadata'
                    ] ?? null,
                ]
            );

        $latest =
            $facility
                ->statementEvidence()
                ->orderByDesc(
                    'statement_to'
                )
                ->orderByDesc(
                    'created_at'
                )
                ->first();

        if ($latest) {
            $facility->update([
                'reported_balance' => $latest
                    ->closing_balance,

                'reported_balance_at' => $latest
                    ->statement_to,

                'minimum_payment' => $latest
                    ->minimum_payment,

                'payment_due_at' => $latest
                    ->payment_due_at,

                'credit_limit' => $latest
                    ->credit_limit
                    ?? $facility->credit_limit,

                'verified' => $latest
                    ->verified,

                'confidence' => $latest
                    ->confidence,
            ]);
        }

        return $statement->refresh();
    }
}
