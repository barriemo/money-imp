<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table): void {
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('commercial_notes')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('reviewed_by');

            $table->uuid('accounting_invoice_id')
                ->nullable()
                ->after('reviewed_at');

            $table->foreign('accounting_invoice_id')
                ->references('id')
                ->on('accounting_invoices')
                ->nullOnDelete();

            $table->index([
                'commercial_status',
                'reviewed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['accounting_invoice_id']);

            $table->dropColumn([
                'reviewed_by',
                'reviewed_at',
                'accounting_invoice_id',
            ]);
        });
    }
};
