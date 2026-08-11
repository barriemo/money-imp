<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'managed_service_templates',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->string('service_type')
                    ->unique();

                $table->string('name');

                $table->text('description')
                    ->nullable();

                $table->boolean('active')
                    ->default(true);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'managed_service_templates'
        );
    }
};
