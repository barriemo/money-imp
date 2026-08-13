<?php

namespace App\Domains\BusinessBrain\Interrogation\Coverage;

use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\CharlieFinding;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\PaymentIdentity;
use App\Models\WorkLog;

class BusinessTruthCoverageService
{
    public function forClient(
        Client $client
    ): BusinessTruthCoverage {
        $invoiceCount =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->count();

        $bankTransactionCount =
            BankTransaction::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->count();

        $paymentIdentityCount =
            PaymentIdentity::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->count();

        $workLogCount =
            WorkLog::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->count();

        $serviceCount =
            ClientService::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->count();

        $openCharlieFindingCount =
            CharlieFinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'status',
                    'open'
                )
                ->count();

        $coverage = collect([
            $invoiceCount > 0,
            $bankTransactionCount > 0,
            $paymentIdentityCount > 0,
            $workLogCount > 0,
            $serviceCount > 0,
            $openCharlieFindingCount > 0,
        ]);

        return new BusinessTruthCoverage(
            client: $client->name,

            invoiceCount: $invoiceCount,

            bankTransactionCount: $bankTransactionCount,

            paymentIdentityCount: $paymentIdentityCount,

            workLogCount: $workLogCount,

            serviceCount: $serviceCount,

            openCharlieFindingCount: $openCharlieFindingCount,

            hasInvoices: $invoiceCount > 0,

            hasBankTransactions: $bankTransactionCount > 0,

            hasPaymentIdentity: $paymentIdentityCount > 0,

            hasWorkLogs: $workLogCount > 0,

            hasServices: $serviceCount > 0,

            hasCharlieFindings: $openCharlieFindingCount > 0,

            confidence: (int) round(
                (
                    $coverage
                        ->filter()
                        ->count()
                    /
                    $coverage->count()
                )
                * 100
            )
        );
    }
}
