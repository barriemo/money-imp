<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapabilityAction extends Model
{
    protected $fillable = [
        'capability_definition_id',
        'name',
        'description',
        'priority',
    ];

    public function capability(): BelongsTo
    {
        return $this->belongsTo(
            CapabilityDefinition::class,
            'capability_definition_id'
        );
    }
}
