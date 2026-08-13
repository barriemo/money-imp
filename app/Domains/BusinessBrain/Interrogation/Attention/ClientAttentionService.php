<?php

namespace App\Domains\BusinessBrain\Interrogation\Attention;

use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\CharlieFinding;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClientAttentionService
{
    public function ranked(): Collection
    {
        return Client::query()
            ->where(
                'status',
                'active'
            )
            ->get()
            ->map(
                fn (Client $client) => $this->position(
                    $client
                )
            )
            ->filter(
                fn (ClientAttentionPosition $position) => $position->score > 0
            )
            ->sortByDesc(
                'score'
            )
            ->values();
    }

    public function position(
        Client $client
    ): ClientAttentionPosition {
        $outstanding =
            (float) AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->sum(
                    'outstanding_amount'
                );

        $overdue =
            (float) AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'due_date',
                    '<',
                    now()
                )
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->sum(
                    'outstanding_amount'
                );

        $unmatchedTransactions =
            BankTransaction::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'match_status',
                    'unmatched'
                )
                ->count();

        $findings =
            CharlieFinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'status',
                    'open'
                );

        $highPriorityFindings =
            (clone $findings)
                ->where(
                    'priority_score',
                    '>=',
                    80
                )
                ->count();

        $highestCharliePriority =
            (int) (
                (clone $findings)
                    ->max(
                        'priority_score'
                    )
                ?? 0
            );

        $lastInvoiceValue =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->max(
                    'invoice_date'
                );

        $lastInvoiceDate =
            $lastInvoiceValue
                ? Carbon::parse(
                    $lastInvoiceValue
                )
                : null;

        $daysSinceLastInvoice =
            $lastInvoiceDate
                ? $lastInvoiceDate->diffInDays(
                    today()
                )
                : null;

        $billingDormant =
            $daysSinceLastInvoice !== null
            && $daysSinceLastInvoice >= 60;

        $score =
            $this->score(
                outstanding: $outstanding,
                overdue: $overdue,
                unmatchedTransactions: $unmatchedTransactions,
                highPriorityFindings: $highPriorityFindings,
                highestCharliePriority: $highestCharliePriority,
                daysSinceLastInvoice: $daysSinceLastInvoice
            );

        return new ClientAttentionPosition(
            clientId: (string) $client->id,

            client: $client->name,

            outstanding: $outstanding,

            overdue: $overdue,

            unmatchedTransactions: $unmatchedTransactions,

            highPriorityFindings: $highPriorityFindings,

            highestCharliePriority: $highestCharliePriority,

            lastInvoiceDate: $lastInvoiceDate,

            daysSinceLastInvoice: $daysSinceLastInvoice,

            billingDormant: $billingDormant,

            reasons: $this->reasons(
                overdue: $overdue,
                unmatchedTransactions: $unmatchedTransactions,
                highPriorityFindings: $highPriorityFindings,
                daysSinceLastInvoice: $daysSinceLastInvoice
            ),

            score: $score
        );
    }

    private function reasons(
        float $overdue,
        int $unmatchedTransactions,
        int $highPriorityFindings,
        ?int $daysSinceLastInvoice
    ): array {
        $reasons = [];

        if ($overdue > 0) {
            $reasons[] =
                sprintf(
                    '£%s is overdue.',
                    number_format(
                        $overdue,
                        2
                    )
                );
        }

        if (
            $daysSinceLastInvoice !== null
            && $daysSinceLastInvoice >= 60
        ) {
            $reasons[] =
                sprintf(
                    'No invoice has been raised for %d days.',
                    $daysSinceLastInvoice
                );
        }

        if ($highPriorityFindings > 0) {
            $reasons[] =
                sprintf(
                    '%d high-priority Charlie finding%s remain open.',
                    $highPriorityFindings,
                    $highPriorityFindings === 1
                        ? ''
                        : 's'
                );
        }

        if ($unmatchedTransactions > 0) {
            $reasons[] =
                sprintf(
                    '%d bank transaction%s remain unmatched.',
                    $unmatchedTransactions,
                    $unmatchedTransactions === 1
                        ? ''
                        : 's'
                );
        }

        return $reasons;
    }

    private function score(
        float $outstanding,
        float $overdue,
        int $unmatchedTransactions,
        int $highPriorityFindings,
        int $highestCharliePriority,
        ?int $daysSinceLastInvoice
    ): int {
        return min(
            100,
            (int) round(
                min(
                    40,
                    $overdue / 500
                )
                +
                min(
                    20,
                    $outstanding / 1000
                )
                +
                min(
                    15,
                    $unmatchedTransactions * 3
                )
                +
                min(
                    15,
                    $highPriorityFindings * 5
                )
                +
                min(
                    10,
                    $highestCharliePriority / 10
                )
                +
                $this->billingDormancyScore(
                    $daysSinceLastInvoice
                )
            )
        );
    }

    private function billingDormancyScore(
        ?int $daysSinceLastInvoice
    ): int {
        if ($daysSinceLastInvoice === null) {
            return 0;
        }

        if ($daysSinceLastInvoice >= 180) {
            return 15;
        }

        if ($daysSinceLastInvoice >= 90) {
            return 10;
        }

        if ($daysSinceLastInvoice >= 60) {
            return 7;
        }

        return 0;
    }
}
