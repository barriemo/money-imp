<?php

namespace App\Domains\BusinessBrain\FinancialPosition;

use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use Carbon\CarbonImmutable;

class FinancialPosition
{
    public function __construct(
        public CashTruth $cash,

        public ReceivablesPosition $receivables,

        public LiabilityPosition $liabilities,

        public CreditTruth $credit,

        public int $confidence,

        public CarbonImmutable $asOf
    ) {}
}
