<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InvestigationCase extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'subject_type',
        'subject_id',
        'subject_name',
        'title',
        'question',
        'status',
        'confidence',
        'current_hypothesis',
        'verdict',
        'opened_at',
        'closed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function experience(): HasOne
    {
        return $this->hasOne(
            BusinessExperience::class,
            'source_investigation_case_id'
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(
            InvestigationEvent::class
        );
    }
}
