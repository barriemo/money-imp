<?php

namespace App\Domains\BusinessBrain\CreditTruth;

use App\Models\CreditStatementEvidence;
use App\Models\ImportBatch;

class CreditStatementImportService
{
    public function __construct(
        private CreditFacilityResolver $facilities,

        private CreditStatementEvidenceService $evidence
    ) {}

    public function import(
        ImportBatch $batch,
        array $statement
    ): CreditStatementEvidence {
        if ($batch->provider !== 'capital_on_tap_pdf') {
            throw new \InvalidArgumentException(
                'Unsupported credit statement batch provider: '.$batch->provider
            );
        }

        $facility =
            $this->facilities
                ->forProvider(
                    $batch->provider
                );

        $evidence =
            $this->evidence
                ->record(
                    facility: $facility,
                    evidence: [
                        'import_batch_id' => $batch->id,

                        'statement_from' => $statement[
                            'statement_from'
                        ] ?? null,

                        'statement_to' => $statement[
                            'statement_to'
                        ] ?? null,

                        'opening_balance' => $statement[
                            'opening_balance'
                        ] ?? null,

                        'closing_balance' => $statement[
                            'closing_balance'
                        ],

                        'minimum_payment' => $statement[
                            'minimum_payment'
                        ] ?? null,

                        'payment_due_at' => $statement[
                            'payment_due_at'
                        ] ?? null,

                        'credit_limit' => $statement[
                            'credit_limit'
                        ] ?? null,

                        'source' => $batch->provider,

                        'verified' => $statement[
                            'verified'
                        ] ?? false,

                        'confidence' => $statement[
                            'confidence'
                        ] ?? 0,

                        'metadata' => [
                            'import_batch_id' => $batch->id,

                            'original_filename' => $batch
                                ->original_filename,
                        ],
                    ]
                );

        $batch->update([
            'status' => 'completed',

            'finished_at' => now(),

            'metadata' => array_merge(
                $batch->metadata ?? [],
                [
                    'credit_facility_id' => $facility->id,

                    'credit_statement_evidence_id' => $evidence->id,
                ]
            ),
        ]);

        return $evidence;
    }
}
