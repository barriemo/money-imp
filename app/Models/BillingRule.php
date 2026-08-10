<?php

namespace App\Models;

use Database\Factories\BillingRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingRule extends MoneyImpModel
{
    /** @use HasFactory<BillingRuleFactory> */
    use HasFactory;
}
