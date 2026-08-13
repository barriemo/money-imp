<?php

namespace App\Domains\BusinessBrain\DeliveryTruth;

use Illuminate\Support\Collection;

class DeliveryGapDetector
{
    public function detect(
        DeliveryTruth $truth
    ): Collection {
        $gaps =
            collect();

        if ($truth->workLogCount === 0) {
            $gaps->push(
                new DeliveryGap(
                    type: 'missing_delivery_evidence',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'No delivery evidence exists',

                    description: 'Money Imp has no recorded work logs for this client, so it cannot prove what work has been delivered or whether it should have become revenue.',

                    value: null,

                    priority: 70,

                    confidence: 100
                )
            );

            return $gaps;
        }

        if ($truth->uninvoicedCommercialValue > 0) {
            $gaps->push(
                new DeliveryGap(
                    type: 'uninvoiced_delivery',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'Recorded commercial delivery is not linked to an invoice',

                    description: sprintf(
                        '£%s of recorded commercial value across %d work logs is not linked to an accounting invoice.',
                        number_format(
                            $truth->uninvoicedCommercialValue,
                            2
                        ),
                        $truth->uninvoicedWorkLogCount
                    ),

                    value: $truth->uninvoicedCommercialValue,

                    priority: 95,

                    confidence: 100
                )
            );
        }

        if (
            $truth->workLogCount > 0
            && $truth->invoiceLinkageConfidence < 100
        ) {
            $gaps->push(
                new DeliveryGap(
                    type: 'incomplete_invoice_linkage',

                    clientId: $truth->clientId,

                    client: $truth->client,

                    title: 'Delivery-to-invoice linkage is incomplete',

                    description: sprintf(
                        '%d of %d work logs are linked to invoices. Delivery invoice-linkage confidence is %d%%.',
                        $truth->invoicedWorkLogCount,
                        $truth->workLogCount,
                        $truth->invoiceLinkageConfidence
                    ),

                    value: null,

                    priority: 80,

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
