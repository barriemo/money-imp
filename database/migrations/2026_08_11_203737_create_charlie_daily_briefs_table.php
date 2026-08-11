<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'charlie_daily_briefs',
            function (Blueprint $table): void {
                $table->uuid('id')->primary();

                $table->date(
                    'brief_date'
                );

                $table->unsignedInteger(
                    'client_count'
                )->default(0);

                $table->unsignedInteger(
                    'attention_count'
                )->default(0);

                $table->unsignedInteger(
                    'new_finding_count'
                )->default(0);

                $table->unsignedInteger(
                    'resolved_finding_count'
                )->default(0);

                $table->decimal(
                    'estimated_monthly_value',
                    12,
                    2
                )->default(0);

                $table->json(
                    'summary'
                )->nullable();

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    'brief_date'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'charlie_daily_briefs'
        );
    }
};
