<?php

namespace App\Domains\Suppliers\Actions;

use App\Models\BankTransaction;
use App\Models\CostAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllocateSupplierTransaction
{
    public function execute(
        BankTransaction $transaction,
        string $purpose,
        ?string $clientId,
        User $user,
        ?int $projectId = null,
    ): void {
        DB::transaction(
            function () use (
                $transaction,
                $purpose,
                $clientId,
                $user,
                $projectId,
            ): void {
                $hasRequiredTarget = match ($purpose) {
                    'client' => $clientId !== null && $clientId !== '',
                    'project' => $projectId !== null && $projectId > 0,
                    'unknown' => false,
                    default => true,
                };

                $transaction->update([
                    'cost_purpose' => $purpose,
                    'cost_review_status' => $hasRequiredTarget
                        ? 'reviewed'
                        : 'needs_review',
                    'cost_reviewed_at' => now(),
                    'cost_reviewed_by' => $user->id,
                ]);

                CostAllocation::query()
                    ->where(
                        'cost_allocatable_type',
                        BankTransaction::class
                    )
                    ->where(
                        'cost_allocatable_id',
                        $transaction->id
                    )
                    ->delete();

                if ($purpose === 'client' && $clientId) {
                    CostAllocation::create([
                        'cost_allocatable_type' => BankTransaction::class,
                        'cost_allocatable_id' => $transaction->id,
                        'client_id' => $clientId,
                        'allocation_type' => 'client',
                        'currency' => $transaction->currency ?? 'GBP',
                        'amount' => abs(
                            (float) $transaction->amount
                        ),
                        'allocation_percent' => 100,
                        'allocated_at' => now(),
                        'allocated_by' => $user->id,
                        'metadata' => [
                            'source' => 'supplier_review',
                        ],
                    ]);

                    return;
                }

                if ($purpose !== 'project' || ! $projectId) {
                    return;
                }

                Project::query()->findOrFail($projectId);

                CostAllocation::create([
                    'cost_allocatable_type' => BankTransaction::class,
                    'cost_allocatable_id' => $transaction->id,
                    'project_id' => $projectId,
                    'allocation_type' => 'project',
                    'currency' => $transaction->currency ?? 'GBP',
                    'amount' => abs(
                        (float) $transaction->amount
                    ),
                    'allocation_percent' => 100,
                    'allocated_at' => now(),
                    'allocated_by' => $user->id,
                    'metadata' => [
                        'source' => 'supplier_review',
                    ],
                ]);
            }
        );
    }
}
