<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_name',
        'request',
        'source',
        'status',
        'classification',
    ];

    public function classifications(): HasMany
    {
        return $this->hasMany(
            ClientRequestClassification::class
        );
    }
}
