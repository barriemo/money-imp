<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Domains\BusinessBrain\Evidence\ClientPaymentEvidenceSummaryService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CanonicalClientRevenueObservationService
{
    public function __construct(
        private DeliveryTruthService $deliveryTruth,
        private ClientPaymentEvidenceSummaryService $paymentEvidence,
    ) {}

    public function forClient(
        Client $client,
        ?CarbonImmutable $asOf = null,
    ): CanonicalClientRevenueObservation {
        $clientId =
            (string) $client->id;

        $invoices =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $clientId
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

        if ($delivery->clientId !== $clientId) {
            throw new InvalidArgumentException(
                'Canonical client revenue observation received delivery evidence for a different client.'
            );
        }

        if ($payment->clientId !== $clientId) {
            throw new InvalidArgumentException(
                'Canonical client revenue observation received payment evidence for a different client.'
            );
        }

        return new CanonicalClientRevenueObservation(
            clientId: $clientId,

            clientName: $client->name,

            accountingInvoiceCount: $invoices->count(),

            accountingGrossInvoicedAmount: (float) $invoices
                ->sum(
                    'gross_amount'
                ),

            accountingReportedPaidAmount: (float) $invoices
                ->sum(
                    'paid_amount'
                ),

            accountingReportedOutstandingAmount: (float) $invoices
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->sum(
                    'outstanding_amount'
                ),

            recordedWorkLogCount: $delivery->workLogCount,

            recordedWorkCommercialValue: $delivery->commercialValue,

            recordedUninvoicedWorkCommercialValue: $delivery->uninvoicedCommercialValue,

            approvedOrImportedPaymentAllocationCount: $payment->approvedPaymentAllocationCount,

            approvedOrImportedPaymentAllocationValue: $payment->approvedPaymentValue,

            truthBoundary: CanonicalClientRevenueObservation::TRUTH_BOUNDARY,

            observedAt: $asOf
                ?? CarbonImmutable::now()
        );
    }
}
