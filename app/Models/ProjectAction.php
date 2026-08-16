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

        $this->recordEvent('approved');
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

        $this->recordEvent('assigned', [
            'owner' => $owner,
        ]);
    }

    public function start(): void
    {
        $this->transitionTo(self::STATUS_IN_PROGRESS);

        $this->recordEvent('started');
    }

    public function complete(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        $this->save();

        $this->recordEvent('completed');
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

        $this->recordEvent('verified');
    }

    protected function transitionTo(string $status): void
    {
        $this->status = $status;
        $this->save();
    }

    protected function recordEvent(
        string $type,
        array $payload = []
    ): void {
        $this->events()->create([
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ProjectActionEvidence::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProjectActionEvent::class);
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(ProjectActionOutcome::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(
            ProjectActionRecommendation::class
        );
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(
            ProjectActionCommitment::class
        );
    }

    public function learnings(): HasMany
    {
        return $this->hasMany(
            ProjectActionLearning::class
        );
    }
}
