<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function agreements(): HasMany
    {
        return $this->hasMany(
            ProjectAgreement::class
        );
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(
            ProjectDeliverable::class
        );
    }

    public function updates(): HasMany
    {
        return $this->hasMany(
            ProjectUpdate::class
        );
    }

    public function communications(): HasMany
    {
        return $this->hasMany(
            ProjectCommunication::class
        );
    }

    public function updateRequests(): HasMany
    {
        return $this->hasMany(
            ProjectUpdateRequest::class
        );
    }

    public function risks(): HasMany
    {
        return $this->hasMany(
            ProjectRisk::class
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(
            ProjectAction::class
        );
    }

    public function costAllocations(): HasMany
    {
        return $this->hasMany(
            CostAllocation::class
        );
    }
}
