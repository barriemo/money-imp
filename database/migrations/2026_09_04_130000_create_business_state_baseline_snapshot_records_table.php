<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_state_baseline_snapshot_records',
            function (Blueprint $table): void {
                $table->uuid('id')
                    ->primary();

                $table->timestamp('as_of');

                $table->json('metrics');

                $table->timestamps();

                $table->index(
                    'as_of'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_state_baseline_snapshot_records'
        );
    }
};
