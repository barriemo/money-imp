<?php

namespace App\Domains\BusinessBrain\DeliveryTruth;

use App\Models\Client;
use App\Models\WorkLog;

class DeliveryTruthService
{
    public function forClient(
        Client $client
    ): DeliveryTruth {
        $logs =
            WorkLog::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->get();

        $invoiced =
            $logs
                ->whereNotNull(
                    'accounting_invoice_id'
                );

        $uninvoiced =
            $logs
                ->whereNull(
                    'accounting_invoice_id'
                );

        $commercialValue =
            (float) $logs
                ->sum(
                    'commercial_value'
                );

        $invoicedCommercialValue =
            (float) $invoiced
                ->sum(
                    'commercial_value'
                );

        $uninvoicedCommercialValue =
            (float) $uninvoiced
                ->sum(
                    'commercial_value'
                );

        return new DeliveryTruth(
            clientId: (string) $client->id,

            client: $client->name,

            workLogCount: $logs->count(),

            invoicedWorkLogCount: $invoiced->count(),

            uninvoicedWorkLogCount: $uninvoiced->count(),

            commercialValue: $commercialValue,

            invoicedCommercialValue: $invoicedCommercialValue,

            uninvoicedCommercialValue: $uninvoicedCommercialValue,

            invoiceLinkageConfidence: $this->confidence(
                workLogCount: $logs->count(),

                invoicedWorkLogCount: $invoiced->count()
            )
        );
    }

    private function confidence(
        int $workLogCount,
        int $invoicedWorkLogCount
    ): int {
        if ($workLogCount === 0) {
            return 0;
        }

        return (int) round(
            ($invoicedWorkLogCount / $workLogCount)
            * 100
        );
    }
}
