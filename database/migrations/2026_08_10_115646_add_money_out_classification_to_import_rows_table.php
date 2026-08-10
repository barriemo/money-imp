<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->uuid('supplier_id')->nullable();
            $table->uuid('expense_category_id')->nullable();
            $table->uuid('client_id')->nullable();

            $table->string('classification_status')
                ->default('unclassified');

            $table->decimal(
                'classification_confidence',
                5,
                2
            )->nullable();

            $table->boolean('remember_classification')
                ->default(false);

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();

            $table->foreign('expense_category_id')
                ->references('id')
                ->on('expense_categories')
                ->nullOnDelete();

            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->nullOnDelete();

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('classification_status');
            $table->index([
                'supplier_id',
                'expense_category_id',
            ]);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['client_id']);
            $table->dropForeign(['reviewed_by']);

            $table->dropColumn([
                'supplier_id',
                'expense_category_id',
                'client_id',
                'classification_status',
                'classification_confidence',
                'remember_classification',
                'reviewed_at',
                'reviewed_by',
            ]);
        });
    }
};
