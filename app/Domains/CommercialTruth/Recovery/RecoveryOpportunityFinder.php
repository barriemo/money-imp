<?php

namespace App\Domains\CommercialTruth\Recovery;

use App\Models\Client;
use App\Models\WorkLog;
use Illuminate\Support\Collection;

class RecoveryOpportunityFinder
{
    public function find(
        Client $client
    ): Collection {
        return WorkLog::query()
            ->where(
                'client_id',
                $client->id
            )
            ->whereNull(
                'accounting_invoice_id'
            )
            ->where(
                'commercial_value',
                '>',
                0
            )
            ->get()
            ->map(
                function (WorkLog $workLog) {
                    return new RecoveryOpportunity(
                        clientId: $workLog->client_id,

                        workLogId: $workLog->id,

                        value: (float) $workLog->commercial_value,

                        confidence: 90,

                        reason: 'Commercial work recorded without invoice recovery.'
                    );
                }
            );
    }
}
