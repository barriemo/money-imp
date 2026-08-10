<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->nullableUuidMorphs('cost_allocatable');

            $table->uuid('client_id')->nullable();
            $table->uuid('client_service_id')->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('GBP');

            /*
             * client
             * internal
             * overhead
             * unallocated
             */
            $table->string('allocation_type')->default('unallocated');

            $table->decimal('allocation_percent', 7, 4)->nullable();

            $table->uuid('allocated_by')->nullable();
            $table->timestamp('allocated_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();

            $table->foreign('client_service_id')
                ->references('id')
                ->on('client_services')
                ->nullOnDelete();

            $table->foreign('allocated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['client_id', 'allocation_type']);
            $table->index(['client_service_id', 'allocation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_allocations');
    }
};
