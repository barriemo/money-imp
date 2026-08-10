<?php

namespace App\Domains\Accounting\Services;

use App\Models\AccountingInvoice;

class InvoiceBalanceService
{
    public function outstanding(AccountingInvoice $invoice): string
    {
        $allocated = $invoice->paymentAllocations()
            ->whereIn('status', ['approved', 'imported'])
            ->sum('amount');

        if ((float) $allocated > 0) {
            return number_format(
                max(0, (float) $invoice->gross_amount - (float) $allocated),
                2,
                '.',
                ''
            );
        }

        return number_format(
            max(0, (float) $invoice->outstanding_amount),
            2,
            '.',
            ''
        );
    }
}
