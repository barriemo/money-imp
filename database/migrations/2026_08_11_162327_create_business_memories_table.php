<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'business_memories',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->nullableUuidMorphs(
                    'subject'
                );

                $table->string('title');

                $table->text('summary')
                    ->nullable();

                $table->string('status')
                    ->default('active');

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'status',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'business_memories'
        );
    }
};
