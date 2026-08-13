<?php

namespace App\Domains\BusinessBrain\Interrogation\Position;

use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\CharlieFinding;
use App\Models\Client;

class BusinessPositionService
{
    public function current(): BusinessPosition
    {
        return new BusinessPosition(
            clientCount: Client::query()
                ->where(
                    'status',
                    'active'
                )
                ->count(),

            invoiceCount: AccountingInvoice::count(),

            grossInvoiced: (float) AccountingInvoice::sum(
                'gross_amount'
            ),

            outstanding: (float) AccountingInvoice::sum(
                'outstanding_amount'
            ),

            bankTransactionCount: BankTransaction::count(),

            unmatchedBankTransactionCount: BankTransaction::query()
                ->where(
                    'match_status',
                    'unmatched'
                )
                ->count(),

            openCharlieFindingCount: CharlieFinding::query()
                ->where(
                    'status',
                    'open'
                )
                ->count(),

            clientsWithOpenCharlieFindings: CharlieFinding::query()
                ->where(
                    'status',
                    'open'
                )
                ->distinct(
                    'client_id'
                )
                ->count(
                    'client_id'
                )
        );
    }
}
