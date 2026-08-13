<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_observation_snapshot_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->timestamp('generated_at');

            $table->json('observations');

            $table->timestamps();

            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_observation_snapshot_records');
    }
};
