<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('executive_actions')
            ->where('status', 'pending')
            ->whereNull('capability_definition_id')
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('type', [
                        'financial_control',
                        'delivery_control',
                        'receivable_recovery',
                    ])
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('type', 'financial_opportunity')
                            ->whereNull('client_id');
                    });
            })
            ->get()
            ->each(function (object $action): void {
                $metadata = json_decode(
                    $action->metadata ?? '{}',
                    true
                );

                $metadata['archive_reason'] =
                    'executive_action_boundary_cleanup';

                DB::table('executive_actions')
                    ->where('id', $action->id)
                    ->update([
                        'status' => 'archived',
                        'metadata' => json_encode($metadata),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('executive_actions')
            ->where('status', 'archived')
            ->whereNull('capability_definition_id')
            ->whereJsonContains(
                'metadata->archive_reason',
                'executive_action_boundary_cleanup'
            )
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('type', [
                        'financial_control',
                        'delivery_control',
                        'receivable_recovery',
                    ])
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('type', 'financial_opportunity')
                            ->whereNull('client_id');
                    });
            })
            ->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);
    }
};
