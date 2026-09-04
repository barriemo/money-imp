<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

final class BusinessStateMetricCatalog
{
    public const SAFE_AVAILABLE_CASH =
        'safe_available_cash';

    public const KNOWN_NET_POSITION =
        'known_net_position';

    public const LEDGER_OUTSTANDING_RECEIVABLES =
        'ledger_outstanding_receivables';

    public const PAYMENTS_WAITING_ALLOCATION =
        'payments_waiting_allocation';

    public const VERIFIED_COLLECTIBLE_RECEIVABLES =
        'verified_collectible_receivables';

    public const KNOWN_LIABILITY_EXPOSURE =
        'known_liability_exposure';

    public const TOTAL_LIABILITY_EXPOSURE =
        'total_liability_exposure';

    public const CLIENT_RECORDS_MARKED_ACTIVE =
        'client_records_marked_active';

    public const GROSS_INVOICED_REVENUE_REPRESENTED =
        'gross_invoiced_revenue_represented';

    public const PAID_REVENUE_ACCORDING_TO_ACCOUNTING =
        'paid_revenue_according_to_accounting';

    public const OUTSTANDING_INVOICED_REVENUE =
        'outstanding_invoiced_revenue';

    public const APPROVED_BANK_BACKED_PAYMENT_EVIDENCE =
        'approved_bank_backed_payment_evidence';

    public const CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE =
        'client_records_with_outstanding_revenue';

    public const CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE =
        'client_records_with_weak_payment_evidence';

    public const RECORDED_UNRECOVERED_WORK_VALUE =
        'recorded_unrecovered_work_value';

    public const CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE =
        'client_records_without_work_evidence';

    public const VERIFIED_BANK_ACCOUNT_RECORDS =
        'verified_bank_account_records';

    public const UNVERIFIED_BANK_ACCOUNT_RECORDS =
        'unverified_bank_account_records';

    public const STALE_BANK_ACCOUNT_RECORDS =
        'stale_bank_account_records';

    public const ALL = [
        self::SAFE_AVAILABLE_CASH,
        self::KNOWN_NET_POSITION,
        self::LEDGER_OUTSTANDING_RECEIVABLES,
        self::PAYMENTS_WAITING_ALLOCATION,
        self::VERIFIED_COLLECTIBLE_RECEIVABLES,
        self::KNOWN_LIABILITY_EXPOSURE,
        self::TOTAL_LIABILITY_EXPOSURE,
        self::CLIENT_RECORDS_MARKED_ACTIVE,
        self::GROSS_INVOICED_REVENUE_REPRESENTED,
        self::PAID_REVENUE_ACCORDING_TO_ACCOUNTING,
        self::OUTSTANDING_INVOICED_REVENUE,
        self::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE,
        self::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE,
        self::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE,
        self::RECORDED_UNRECOVERED_WORK_VALUE,
        self::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE,
        self::VERIFIED_BANK_ACCOUNT_RECORDS,
        self::UNVERIFIED_BANK_ACCOUNT_RECORDS,
        self::STALE_BANK_ACCOUNT_RECORDS,
    ];
}
