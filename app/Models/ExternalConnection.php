<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalConnection extends MoneyImpModel
{
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(ExternalRecord::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
