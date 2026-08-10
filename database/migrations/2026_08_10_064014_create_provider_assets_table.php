<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('provider_id');

            $table->string('asset_type');
            $table->string('name');
            $table->string('external_reference')->nullable();

            $table->string('status')->default('active');

            $table->string('billing_cycle')->nullable();

            $table->decimal('current_cost', 15, 2)->nullable();
            $table->string('currency', 3)->default('GBP');

            $table->date('started_on')->nullable();
            $table->date('renews_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->cascadeOnDelete();

            $table->index(['provider_id', 'asset_type']);
            $table->index(['status', 'renews_on']);
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_assets');
    }
};
