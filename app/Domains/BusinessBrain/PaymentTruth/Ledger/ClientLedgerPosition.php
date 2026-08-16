<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Ledger;

class ClientLedgerPosition
{
    public function __construct(
        public string $clientId,

        public ?string $clientName,

        public ?string $firstPaymentAt,

        public ?string $lastPaymentAt,

        public ?string $firstInvoiceAt,

        public ?string $lastInvoiceAt,

        public int $paymentCount,

        public float $cashReceived,

        public int $invoiceCount,

        public float $invoicedDuringPaymentWindow,

        public float $accountingReportedPaid,

        public float $accountingReportedOutstanding,

        public float $ledgerDifference,

        public bool $openingHistoryIncomplete,

        public bool $accountingHistoryAppearsIncomplete,

        public bool $bankEvidenceMayBeIncomplete
    ) {}
}
