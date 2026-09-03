<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Ledger;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ClientLedgerAnalysisService
{
    public function __construct(
        private CanonicalPaymentEvidenceService $payments
    ) {}

    /**
     * Ledger truth must start from the union of known commercial evidence:
     *
     * - clients with canonical payment evidence
     * - clients with accounting invoice evidence
     *
     * A client with invoices but no recognised payment must not disappear
     * from debtor / ledger intelligence.
     *
     * @return Collection<int, ClientLedgerPosition>
     */
    public function current(): Collection
    {
        $paymentsByClient =
            $this->payments
                ->customerPayments()
                ->filter(
                    fn ($payment) => $payment->clientId !== null
                )
                ->groupBy(
                    'clientId'
                );

        $invoiceClientIds =
            AccountingInvoice::query()
                ->whereNotNull(
                    'client_id'
                )
                ->where(
                    'gross_amount',
                    '>',
                    0
                )
                ->pluck(
                    'client_id'
                );

        $clientIds =
            $paymentsByClient
                ->keys()
                ->concat(
                    $invoiceClientIds
                )
                ->filter()
                ->unique()
                ->values();

        return $clientIds
            ->map(
                fn ($clientId) => $this->position(
                    (string) $clientId,
                    $paymentsByClient->get(
                        $clientId,
                        collect()
                    )
                )
            )
            ->values();
    }

    private function position(
        string $clientId,
        Collection $payments
    ): ClientLedgerPosition {
        $payments =
            $payments
                ->sortBy(
                    'date'
                )
                ->values();

        $firstPaymentAt =
            $payments
                ->first()
                ?->date;

        $lastPaymentAt =
            $payments
                ->last()
                ?->date;

        $allInvoices =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $clientId
                )
                ->where(
                    'gross_amount',
                    '>',
                    0
                )
                ->orderBy(
                    'invoice_date'
                )
                ->get();

        $firstInvoiceAt =
            $allInvoices
                ->whereNotNull(
                    'invoice_date'
                )
                ->first()
                ?->invoice_date
                ?->toDateString();

        $lastInvoiceAt =
            $allInvoices
                ->whereNotNull(
                    'invoice_date'
                )
                ->last()
                ?->invoice_date
                ?->toDateString();

        /*
         * Preserve the existing evidence-window behaviour for clients
         * where bank evidence exists.
         *
         * For invoice-only clients there is no payment window, so all
         * invoice evidence is visible to the ledger position.
         */
        if (
            $firstPaymentAt !== null
            && $lastPaymentAt !== null
        ) {
            $invoiceWindowStart =
                CarbonImmutable::parse(
                    $firstPaymentAt
                )
                    ->startOfMonth()
                    ->toDateString();

            $invoices =
                AccountingInvoice::query()
                    ->where(
                        'client_id',
                        $clientId
                    )
                    ->where(
                        'gross_amount',
                        '>',
                        0
                    )
                    ->where(
                        function ($query) use (
                            $invoiceWindowStart,
                            $lastPaymentAt
                        ): void {
                            $query
                                ->whereNull(
                                    'invoice_date'
                                )
                                ->orWhereBetween(
                                    'invoice_date',
                                    [
                                        $invoiceWindowStart,
                                        $lastPaymentAt,
                                    ]
                                );
                        }
                    )
                    ->get();

            $openingHistoryIncomplete =
                AccountingInvoice::query()
                    ->where(
                        'client_id',
                        $clientId
                    )
                    ->whereDate(
                        'invoice_date',
                        '<',
                        $invoiceWindowStart
                    )
                    ->exists();
        } else {
            $invoices =
                $allInvoices;

            $openingHistoryIncomplete =
                false;
        }

        $cashReceived =
            round(
                (float) $payments
                    ->sum(
                        'amount'
                    ),
                2
            );

        $invoiced =
            round(
                (float) $invoices
                    ->sum(
                        'gross_amount'
                    ),
                2
            );

        $accountingReportedPaid =
            round(
                (float) $invoices
                    ->sum(
                        'paid_amount'
                    ),
                2
            );

        $accountingReportedOutstanding =
            round(
                (float) $invoices
                    ->sum(
                        'outstanding_amount'
                    ),
                2
            );

        $client =
            Client::query()
                ->find(
                    $clientId
                );

        return new ClientLedgerPosition(
            clientId: $clientId,

            clientName: $client?->name,

            firstPaymentAt: $firstPaymentAt,

            lastPaymentAt: $lastPaymentAt,

            firstInvoiceAt: $firstInvoiceAt,

            lastInvoiceAt: $lastInvoiceAt,

            paymentCount: $payments->count(),

            cashReceived: $cashReceived,

            invoiceCount: $invoices->count(),

            invoicedDuringPaymentWindow: $invoiced,

            accountingReportedPaid: $accountingReportedPaid,

            accountingReportedOutstanding: $accountingReportedOutstanding,

            ledgerDifference: round(
                $cashReceived
                - $invoiced,
                2
            ),

            openingHistoryIncomplete: $openingHistoryIncomplete,

            accountingHistoryAppearsIncomplete: $firstPaymentAt !== null
                && $firstInvoiceAt !== null
                && $firstInvoiceAt > $firstPaymentAt,

            bankEvidenceMayBeIncomplete: $accountingReportedPaid
                > $cashReceived
        );
    }
}
