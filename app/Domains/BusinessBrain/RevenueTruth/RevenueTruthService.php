<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Domains\BusinessBrain\Evidence\ClientPaymentEvidenceSummaryService;
use App\Models\AccountingInvoice;
use App\Models\Client;

class RevenueTruthService
{
    public function __construct(
        private ClientPaymentEvidenceSummaryService $paymentEvidence,

        private DeliveryTruthService $deliveryTruth
    ) {}

    public function forClient(
        Client $client
    ): RevenueTruth {
        $invoices =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->get();

        $delivery =
            $this->deliveryTruth
                ->forClient(
                    $client
                );

        $payment =
            $this->paymentEvidence
                ->forClient(
                    $client
                );

        $grossInvoiced =
            (float) $invoices
                ->sum(
                    'gross_amount'
                );

        $paidAccordingToAccounting =
            (float) $invoices
                ->sum(
                    'paid_amount'
                );

        $outstanding =
            (float) $invoices
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->sum(
                    'outstanding_amount'
                );

        return new RevenueTruth(
            clientId: (string) $client->id,

            client: $client->name,

            invoiceCount: $invoices->count(),

            grossInvoiced: $grossInvoiced,

            paidAccordingToAccounting: $paidAccordingToAccounting,

            outstanding: $outstanding,

            workLogCount: $delivery->workLogCount,

            workCommercialValue: $delivery->commercialValue,

            unrecoveredWorkValue: $delivery->uninvoicedCommercialValue,

            bankVerifiedPaymentValue: $payment
                ->approvedPaymentValue,

            paymentEvidenceConfidence: $payment
                ->confidence,

            commercialConfidence: $this->commercialConfidence(
                workLogCount: $delivery->workLogCount,

                invoiceCount: $invoices->count(),

                paymentEvidenceConfidence: $payment->confidence
            )
        );
    }

    private function commercialConfidence(
        int $workLogCount,
        int $invoiceCount,
        int $paymentEvidenceConfidence
    ): int {
        $score = 0;

        if ($invoiceCount > 0) {
            $score += 40;
        }

        if ($workLogCount > 0) {
            $score += 30;
        }

        $score += (int) round(
            $paymentEvidenceConfidence * 0.3
        );

        return min(
            100,
            $score
        );
    }
}
