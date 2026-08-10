<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends MoneyImpModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(ClientService::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(AccountingInvoice::class);
    }

    public function assetAllocations(): HasMany
    {
        return $this->hasMany(ClientAssetAllocation::class);
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(CostAllocation::class);
    }

    public function paymentIdentities(): HasMany
    {
        return $this->hasMany(PaymentIdentity::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function billingRules(): HasMany
    {
        return $this->hasMany(BillingRule::class);
    }
}
