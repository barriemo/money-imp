<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_services', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('client_id');

            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->default('service');
            $table->string('status')->default('active');

            $table->text('description')->nullable();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->decimal('target_margin_percent', 7, 4)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnDelete();

            $table->index(['client_id', 'status']);
            $table->index(['client_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_services');
    }
};
