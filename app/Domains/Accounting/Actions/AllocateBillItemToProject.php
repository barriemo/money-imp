<?php

namespace App\Domains\Accounting\Actions;

use App\Models\AccountingBillItem;
use App\Models\CostAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocateBillItemToProject
{
    public function execute(
        AccountingBillItem $item,
        Project $project,
        float $requestedAmount,
        User $user,
    ): CostAllocation {
        return DB::transaction(function () use (
            $item,
            $project,
            $requestedAmount,
            $user,
        ): CostAllocation {
            $item = AccountingBillItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            $amount = round($requestedAmount, 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Project allocation must be greater than zero.',
                ]);
            }

            $alreadyAllocated = (float) $item
                ->costAllocations()
                ->sum('amount');

            $remaining = round(
                max(
                    0,
                    (float) $item->gross_amount - $alreadyAllocated
                ),
                2
            );

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Only £%s remains available for allocation on this bill item.',
                        number_format($remaining, 2)
                    ),
                ]);
            }

            $percent = round(
                ($amount / (float) $item->gross_amount) * 100,
                4
            );

            return $item->costAllocations()->create([
                'project_id' => $project->id,
                'amount' => $amount,
                'currency' => 'GBP',
                'allocation_type' => 'project',
                'allocation_percent' => $percent,
                'allocated_at' => now(),
                'allocated_by' => $user->id,
                'metadata' => [
                    'source' => 'project_allocation',
                ],
            ]);
        });
    }
}
