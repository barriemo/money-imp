<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->string('cost_purpose')
                ->nullable();

            $table->string('cost_review_status')
                ->default('unreviewed');

            $table->timestamp('cost_reviewed_at')
                ->nullable();

            $table->foreignUuid('cost_reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->index([
                'cost_review_status',
                'cost_purpose',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropForeign([
                'cost_reviewed_by',
            ]);

            $table->dropIndex([
                'cost_review_status',
                'cost_purpose',
            ]);

            $table->dropColumn([
                'cost_purpose',
                'cost_review_status',
                'cost_reviewed_at',
                'cost_reviewed_by',
            ]);
        });
    }
};
