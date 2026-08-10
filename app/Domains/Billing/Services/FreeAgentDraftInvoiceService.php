<?php

namespace App\Domains\Billing\Services;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use Carbon\CarbonImmutable;
use RuntimeException;

class FreeAgentDraftInvoiceService
{
    public function __construct(
        private readonly FreeAgentClient $freeAgent,
    ) {}

    public function createMonthlyDraft(
        Client $client,
        CarbonImmutable $month
    ): array {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->firstOrFail();

        $existing = AccountingInvoice::query()
            ->where('client_id', $client->id)
            ->whereBetween('invoice_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->exists();

        if ($existing) {
            throw new RuntimeException(
                'An invoice already exists for this client in '
                .$month->format('F Y').'.'
            );
        }

        $sourceInvoice = AccountingInvoice::query()
            ->where('client_id', $client->id)
            ->where('invoice_date', '<', $month->startOfMonth())
            ->orderByDesc('invoice_date')
            ->first();

        if (! $sourceInvoice) {
            throw new RuntimeException(
                'No previous invoice exists to duplicate.'
            );
        }

        $sourceRecord = ExternalRecord::query()
            ->where('recordable_type', AccountingInvoice::class)
            ->where('recordable_id', $sourceInvoice->id)
            ->where('external_connection_id', $connection->id)
            ->where('resource_type', 'invoice')
            ->first();

        if (! $sourceRecord?->external_reference) {
            throw new RuntimeException(
                'Previous invoice has no FreeAgent mapping.'
            );
        }

        $sourceId = basename(
            parse_url(
                $sourceRecord->external_reference,
                PHP_URL_PATH
            )
        );

        $duplicated = $this->freeAgent->post(
            $connection,
            'invoices/'.$sourceId.'/duplicate'
        );

        $draft = $duplicated['invoice'] ?? null;

        if (! is_array($draft) || empty($draft['url'])) {
            throw new RuntimeException(
                'FreeAgent did not return the duplicated invoice.'
            );
        }

        $draftId = basename(
            parse_url($draft['url'], PHP_URL_PATH)
        );

        $invoiceDate = $this->targetInvoiceDate(
            $sourceInvoice,
            $month
        );

        $paymentDays = $this->paymentTerms($sourceInvoice);

        $dueDate = $invoiceDate->addDays($paymentDays);

        $updated = $this->freeAgent->put(
            $connection,
            'invoices/'.$draftId,
            [
                'invoice' => [
                    'dated_on' => $invoiceDate->toDateString(),
                    'due_on' => $dueDate->toDateString(),
                ],
            ]
        );

        return $updated['invoice'] ?? $draft;
    }

    private function targetInvoiceDate(
        AccountingInvoice $source,
        CarbonImmutable $month
    ): CarbonImmutable {
        $day = min(
            $source->invoice_date?->day ?? 1,
            $month->daysInMonth
        );

        return $month->setDay($day);
    }

    private function paymentTerms(
        AccountingInvoice $source
    ): int {
        if (
            $source->invoice_date
            && $source->due_date
        ) {
            return max(
                0,
                (int) $source->invoice_date
                    ->startOfDay()
                    ->diffInDays(
                        $source->due_date->startOfDay()
                    )
            );
        }

        return 7;
    }
}
