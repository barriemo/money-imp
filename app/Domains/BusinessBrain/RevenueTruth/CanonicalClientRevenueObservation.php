<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CanonicalClientRevenueObservation
{
    public const TRUTH_BOUNDARY =
        'Accounting invoice amounts are source-recorded accounting values; accounting-reported outstanding is not verified collectible revenue. Work-log commercial values are recorded work evidence, not proof of contractual entitlement or recoverability. Approved or imported payment allocation value is allocation evidence, not proof that all payment truth is complete. This observation does not establish a billing obligation, determine what should be billed, collected, chased, prioritised or recovered, and zero observed values do not prove that no unrecorded revenue entitlement exists.';

    public function __construct(
        public string $clientId,
        public string $clientName,
        public int $accountingInvoiceCount,
        public float $accountingGrossInvoicedAmount,
        public float $accountingReportedPaidAmount,
        public float $accountingReportedOutstandingAmount,
        public int $recordedWorkLogCount,
        public float $recordedWorkCommercialValue,
        public float $recordedUninvoicedWorkCommercialValue,
        public int $approvedOrImportedPaymentAllocationCount,
        public float $approvedOrImportedPaymentAllocationValue,
        public string $truthBoundary,
        public CarbonImmutable $observedAt,
    ) {
        if (trim($this->clientId) === '') {
            throw new InvalidArgumentException(
                'Canonical client revenue observation requires a client id.'
            );
        }

        if (trim($this->clientName) === '') {
            throw new InvalidArgumentException(
                'Canonical client revenue observation requires a client name.'
            );
        }

        foreach (
            [
                'accountingInvoiceCount' => $this->accountingInvoiceCount,

                'recordedWorkLogCount' => $this->recordedWorkLogCount,

                'approvedOrImportedPaymentAllocationCount' => $this->approvedOrImportedPaymentAllocationCount,
            ] as $field => $value
        ) {
            if ($value < 0) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Canonical client revenue observation %s cannot be negative.',
                        $field
                    )
                );
            }
        }

        foreach (
            [
                'accountingGrossInvoicedAmount' => $this->accountingGrossInvoicedAmount,

                'accountingReportedPaidAmount' => $this->accountingReportedPaidAmount,

                'accountingReportedOutstandingAmount' => $this->accountingReportedOutstandingAmount,

                'recordedWorkCommercialValue' => $this->recordedWorkCommercialValue,

                'recordedUninvoicedWorkCommercialValue' => $this->recordedUninvoicedWorkCommercialValue,

                'approvedOrImportedPaymentAllocationValue' => $this->approvedOrImportedPaymentAllocationValue,
            ] as $field => $value
        ) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Canonical client revenue observation %s must be finite.',
                        $field
                    )
                );
            }
        }

        if (trim($this->truthBoundary) === '') {
            throw new InvalidArgumentException(
                'Canonical client revenue observation requires an explicit truth boundary.'
            );
        }
    }
}
