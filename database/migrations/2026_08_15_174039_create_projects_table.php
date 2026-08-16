<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('status')
                ->default('active');

            $table->string('health')
                ->default('unknown');

            $table->string('owner')
                ->nullable();

            $table->decimal(
                'commercial_value',
                12,
                2
            )->nullable();

            $table->string('billing_model')
                ->nullable();

            $table->date('start_date')
                ->nullable();

            $table->date('target_date')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
