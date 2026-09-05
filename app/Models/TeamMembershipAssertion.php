<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class TeamMembershipAssertion extends MoneyImpModel
{
    public const UPDATED_AT = null;

    public const STATUS_MEMBER =
        'member';

    public const STATUS_NOT_MEMBER =
        'not_member';

    public const TRUTH_BOUNDARY =
        'A team-membership assertion is explicit human-confirmed membership truth for an existing User. A User account alone does not establish team membership. Membership does not establish employment, verified work authorship, contracted capacity, available capacity, utilisation, allocation, billability, recoverability, cost, margin, performance or priority. Absence of a current assertion means membership is not established, not that the User is not a team member.';

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',

            'effective_to' => 'date',

            'reviewed_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::updating(
            function (): void {
                throw new LogicException(
                    'Team membership assertions are immutable. Create a superseding assertion instead.'
                );
            }
        );

        self::deleting(
            function (): void {
                throw new LogicException(
                    'Team membership assertions are immutable.'
                );
            }
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'supersedes_team_membership_assertion_id'
        );
    }
}
