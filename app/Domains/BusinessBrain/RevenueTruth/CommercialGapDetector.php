<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use Illuminate\Support\Collection;

class CommercialGapDetector
{
    public function detect(
        RevenueTruth $truth
    ): Collection {
        $gaps =
            collect();

        if ($truth->unrecoveredWorkValue > 0) {
            $gaps->push(
                new CommercialGap(
                    type: 'unbilled_work',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'Completed work has not been invoiced',

                    description: sprintf(
                        '£%s of recorded commercial work is not linked to an invoice.',
                        number_format(
                            $truth->unrecoveredWorkValue,
                            2
                        )
                    ),

                    value: $truth->unrecoveredWorkValue,

                    priority: 95,

                    confidence: 100
                )
            );
        }

        if ($truth->outstanding > 0) {
            $gaps->push(
                new CommercialGap(
                    type: 'outstanding_revenue',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'Invoiced revenue remains unpaid',

                    description: sprintf(
                        '£%s remains outstanding.',
                        number_format(
                            $truth->outstanding,
                            2
                        )
                    ),

                    value: $truth->outstanding,

                    priority: 90,

                    confidence: 100
                )
            );
        }

        if (
            $truth->paidAccordingToAccounting > 0
            && $truth->paymentEvidenceConfidence < 100
        ) {
            $gaps->push(
                new CommercialGap(
                    type: 'weak_payment_evidence',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'Accounting payment claims lack complete bank evidence',

                    description: sprintf(
                        'Accounting records £%s as paid, while approved bank-backed payment evidence totals £%s. Payment evidence confidence is %d%%.',
                        number_format(
                            $truth->paidAccordingToAccounting,
                            2
                        ),
                        number_format(
                            $truth->bankVerifiedPaymentValue,
                            2
                        ),
                        $truth->paymentEvidenceConfidence
                    ),

                    value: null,

                    priority: 80,

                    confidence: 100
                )
            );
        }

        if ($truth->workLogCount === 0) {
            $gaps->push(
                new CommercialGap(
                    type: 'missing_work_evidence',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'Delivery evidence is missing',

                    description: 'Money Imp has no work-log evidence for this client, so it cannot yet prove whether all completed work became revenue.',

                    value: null,

                    priority: 70,

                    confidence: 100
                )
            );
        }

        return $gaps
            ->sortByDesc(
                'priority'
            )
            ->values();
    }
}
