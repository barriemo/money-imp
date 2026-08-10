<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'client_id',
        'user_id',
        'description',
        'minutes',
        'performed_at',
        'billing_hint',
        'commercial_status',
        'rate_snapshot',
        'commercial_value',
        'commercial_notes',
        'reviewed_by',
        'reviewed_at',
        'accounting_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'rate_snapshot' => 'decimal:2',
            'commercial_value' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function accountingInvoice(): BelongsTo
    {
        return $this->belongsTo(
            AccountingInvoice::class
        );
    }
}
