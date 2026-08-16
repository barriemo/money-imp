<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExperience extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_investigation_case_id',
        'fingerprint',
        'type',
        'subject_type',
        'subject_id',
        'subject_name',
        'title',
        'summary',
        'outcome',
        'confidence',
        'importance',
        'hypothesis',
        'lessons',
        'evidence_summary',
        'metadata',
        'experienced_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'importance' => 'integer',
            'lessons' => 'array',
            'evidence_summary' => 'array',
            'metadata' => 'array',
            'experienced_at' => 'datetime',
        ];
    }

    public function sourceInvestigation(): BelongsTo
    {
        return $this->belongsTo(
            InvestigationCase::class,
            'source_investigation_case_id'
        );
    }
}
