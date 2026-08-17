<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapabilityDefinition extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'area',
        'owner',
        'purpose',
        'layers',
        'status',
    ];

    protected $casts = [
        'layers' => 'array',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(
            CapabilityAction::class
        );
    }

    public function executiveActions(): HasMany
    {
        return $this->hasMany(
            ExecutiveAction::class
        );
    }
}
