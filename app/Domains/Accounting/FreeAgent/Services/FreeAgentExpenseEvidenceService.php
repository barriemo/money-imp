<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Domains\Accounting\Contracts\ExpenseEvidenceProvider;
use App\Domains\Accounting\FreeAgent\Evidence\FreeAgentExpenseEvidence;
use App\Models\ExternalConnection;
use Illuminate\Support\Collection;

final class FreeAgentExpenseEvidenceService implements ExpenseEvidenceProvider
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    /**
     * @return Collection<int, FreeAgentExpenseEvidence>
     */
    public function current(): Collection
    {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            return collect();
        }

        $page = 1;
        $expenses = collect();

        do {
            $response = $this->client->get(
                $connection,
                'expenses',
                [
                    'page' => $page,
                    'per_page' => 100,
                ]
            );

            $records = $response['expenses'] ?? [];

            $expenses = $expenses->merge(
                collect($records)->map(
                    fn (array $expense) => new FreeAgentExpenseEvidence(
                        expenseId: (string) basename($expense['url'] ?? ''),
                        description: (string) ($expense['description'] ?? ''),
                        date: (string) ($expense['dated_on'] ?? ''),
                        grossAmount: abs((float) ($expense['gross_value'] ?? 0)),
                        vatAmount: abs((float) ($expense['sales_tax_value'] ?? 0)),
                        vatRate: (float) ($expense['sales_tax_rate'] ?? 0),
                        category: isset($expense['category'])
                            ? basename($expense['category'])
                            : null,
                        user: isset($expense['user'])
                            ? basename($expense['user'])
                            : null,
                    )
                )
            );

            $page++;
        } while (count($records) === 100);

        return $expenses;
    }
}
