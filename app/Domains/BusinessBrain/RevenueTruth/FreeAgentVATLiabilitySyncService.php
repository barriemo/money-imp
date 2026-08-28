<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Models\ExternalConnection;
use App\Models\Liability;
use Carbon\Carbon;

class FreeAgentVATLiabilitySyncService
{
    public function sync(
        ExternalConnection $connection
    ): array {
        $client = app(FreeAgentClient::class);

        $response = $client->get(
            $connection,
            '/vat_returns',
            []
        );

        $returns = collect(
            $response['vat_returns'] ?? []
        );

        $seen = 0;
        $open = 0;
        $closed = 0;
        $ignored = 0;

        foreach ($returns as $vatReturn) {
            foreach ($vatReturn['payments'] ?? [] as $payment) {
                $seen++;

                $amount = (float) ($payment['amount_due'] ?? 0);
                $status = strtolower(
                    (string) ($payment['status'] ?? '')
                );
                $label = strtolower(
                    (string) ($payment['label'] ?? '')
                );

                /*
                 * Refunds and zero-payment returns are not liabilities.
                 */
                if (
                    $amount <= 0
                    || str_contains($label, 'refund')
                    || str_contains($label, 'no payment')
                ) {
                    $ignored++;

                    continue;
                }

                $periodEnd = $vatReturn['period_ends_on'] ?? null;

                if (! $periodEnd) {
                    $ignored++;

                    continue;
                }

                $name = 'FreeAgent VAT '.$periodEnd;

                $isOpen = $status === 'unpaid';

                Liability::updateOrCreate(
                    [
                        'type' => 'vat',
                        'name' => $name,
                    ],
                    [
                        'amount' => $amount,
                        'due_date' => ! empty($payment['due_on'])
                            ? Carbon::parse(
                                $payment['due_on']
                            )->toDateString()
                            : null,
                        'status' => $isOpen
                            ? 'open'
                            : 'closed',
                        'source' => 'freeagent_vat_return',
                        'verified' => true,
                        'confidence' => 100,
                        'notes' => $payment['label'] ?? null,
                        'metadata' => [
                            'period_starts_on' => $vatReturn['period_starts_on'] ?? null,

                            'period_ends_on' => $periodEnd,

                            'payment_status' => $payment['status'] ?? null,

                            'freeagent_url' => $vatReturn['url'] ?? null,

                            'source' => 'freeagent_vat_return',
                        ],
                    ]
                );

                if ($isOpen) {
                    $open++;
                } else {
                    $closed++;
                }
            }
        }

        return [
            'returns' => $returns->count(),
            'payments_seen' => $seen,
            'open' => $open,
            'closed' => $closed,
            'ignored' => $ignored,
        ];
    }
}
