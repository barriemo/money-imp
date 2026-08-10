<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('bank_name')->nullable();

            $table->string('currency', 3)->default('GBP');
            $table->string('account_type')->nullable();

            // Store only what we need for recognition/display.
            $table->string('account_identifier')->nullable();
            $table->string('account_last_four', 4)->nullable();

            $table->string('status')->default('active');

            $table->decimal('current_balance', 15, 2)->nullable();
            $table->timestamp('balance_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
