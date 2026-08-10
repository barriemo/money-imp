<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('legal_name')->nullable();

            $table->string('type')->default('supplier');
            $table->string('status')->default('active');

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('company_number')->nullable();
            $table->string('vat_number')->nullable();

            $table->string('currency', 3)->default('GBP');

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'name']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
