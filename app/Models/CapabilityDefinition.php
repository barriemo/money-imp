<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapabilityDefinition extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'area',
        'owner',
        'purpose',
        'layers',
    ];

    protected $casts = [
        'layers' => 'array',
    ];
}