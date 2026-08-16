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
     * @return Collection<int, ClientLedgerPosition>
     */
    public function current(): Collection
    {
        return $this->payments
            ->customerPayments()
            ->filter(
                fn ($payment) => $payment->clientId !== null
            )
            ->groupBy(
                'clientId'
            )
            ->map(
                function (Collection $payments, string $clientId): ClientLedgerPosition {
                    $payments =
                        $payments
                            ->sortBy('date')
                            ->values();

                    $firstPaymentAt =
                        $payments
                            ->first()
                            ->date;

                    $lastPaymentAt =
                        $payments
                            ->last()
                            ->date;

                    $invoiceWindowStart =
                        CarbonImmutable::parse(
                            $firstPaymentAt
                        )
                            ->startOfMonth()
                            ->toDateString();

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

                    $cashReceived =
                        round(
                            (float) $payments
                                ->sum('amount'),
                            2
                        );

                    $invoiced =
                        round(
                            (float) $invoices
                                ->sum('gross_amount'),
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

                        accountingReportedPaid: round(
                            (float) $invoices
                                ->sum('paid_amount'),
                            2
                        ),

                        accountingReportedOutstanding: round(
                            (float) $invoices
                                ->sum('outstanding_amount'),
                            2
                        ),

                        ledgerDifference: round(
                            $cashReceived
                            - $invoiced,
                            2
                        ),

                        openingHistoryIncomplete: AccountingInvoice::query()
                            ->where(
                                'client_id',
                                $clientId
                            )
                            ->whereDate(
                                'invoice_date',
                                '<',
                                $invoiceWindowStart
                            )
                            ->exists(),

                        accountingHistoryAppearsIncomplete: $firstInvoiceAt !== null
                            && $firstInvoiceAt > $firstPaymentAt,

                        bankEvidenceMayBeIncomplete: $invoices->sum(
                            'paid_amount'
                        ) > $cashReceived
                    );
                }
            )
            ->values();
    }
}
