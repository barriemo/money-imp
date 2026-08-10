<?php

namespace App\Models;

use Database\Factories\BankTransactionExplanationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankTransactionExplanation extends MoneyImpModel
{
    /** @use HasFactory<BankTransactionExplanationFactory> */
    use HasFactory;
}
