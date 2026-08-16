<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectAction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public const STATUS_OPEN = 'open';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VERIFIED = 'verified';

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function approve(): void
    {
        $this->transitionTo(self::STATUS_APPROVED);
    }

    public function assignTo(string $owner): void
    {
        if (! in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_OPEN,
        ])) {
            throw new DomainException(
                'Only open or approved actions can be assigned.'
            );
        }

        $this->assigned_to = $owner;
        $this->status = self::STATUS_ASSIGNED;
        $this->save();
    }

    public function start(): void
    {
        $this->transitionTo(self::STATUS_IN_PROGRESS);
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();
    }

    public function verify(): void
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            throw new DomainException(
                'Only completed actions can be verified.'
            );
        }

        $this->status = self::STATUS_VERIFIED;
        $this->verified_at = now();
        $this->save();
    }

    protected function transitionTo(string $status): void
    {
        $this->status = $status;
        $this->save();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ProjectActionEvidence::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectActionEvent::class);
    }
}
