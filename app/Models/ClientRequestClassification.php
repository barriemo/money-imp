<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRequestClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_request_id',
        'classification',
        'confidence',
        'reason',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ClientRequest::class,
            'client_request_id'
        );
    }
}
