<?php

namespace App\Domains\Dashboard\MorningBrief;

use App\Domains\BusinessBrain\CommercialBrief\CommercialBriefBuilder;
use App\Models\Client;
use App\Models\WorkLog;

class CommercialAttentionBuilder
{
    public function __construct(
        private CommercialBriefBuilder $commercialBriefs,
    ) {}

    public function build(): CommercialAttention
    {
        $clients =
            Client::query()
                ->where('status', 'active')
                ->get();

        $briefs =
            $clients->map(
                function (Client $client) {
                    return [
                        'client' => $client,

                        'brief' => $this->commercialBriefs
                            ->build($client),
                    ];
                }
            );

        $attention =
            $briefs->filter(
                fn ($item) => $item['brief']->health === 'attention_required'
            );

        $priority =
            $attention
                ->sortByDesc(
                    fn ($item) => $item['brief']->recoveryValue
                )
                ->take(5)
                ->map(
                    function ($item) {
                        return [
                            'client' => $item['client']->name,

                            'value' => $item['brief']
                                ->recoveryValue,
                        ];
                    }
                )
                ->values()
                ->all();

        return new CommercialAttention(
            clientCount: $attention->count(),

            recoverableValue: $attention->sum(
                fn ($item) => $item['brief']->recoveryValue
            ),

            openWorkLogs: WorkLog::query()
                ->whereIn(
                    'commercial_status',
                    [
                        'unreviewed',
                        'invoice',
                    ]
                )
                ->whereNull(
                    'accounting_invoice_id'
                )
                ->count(),

            highPriorityClients: $priority,
        );
    }
}
