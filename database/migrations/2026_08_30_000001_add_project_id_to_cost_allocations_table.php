<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_allocations', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            $table->index([
                'project_id',
                'allocation_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cost_allocations', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex([
                'cost_allocations_project_id_allocation_type_index',
            ]);
            $table->dropColumn('project_id');
        });
    }
};
