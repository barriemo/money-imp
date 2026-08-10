<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('import_batch_id');
            $table->uuid('bank_transaction_id')->nullable();

            $table->unsignedInteger('row_number')->nullable();

            $table->date('transaction_date')->nullable();

            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('GBP');

            $table->text('description')->nullable();
            $table->string('merchant')->nullable();
            $table->string('reference')->nullable();

            $table->string('row_hash')->index();

            $table->string('status')->default('pending');
            $table->string('failure_reason')->nullable();

            $table->json('raw_payload')->nullable();
            $table->json('normalised_payload')->nullable();

            $table->timestamps();

            $table->foreign('import_batch_id')
                ->references('id')
                ->on('import_batches')
                ->cascadeOnDelete();

            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->nullOnDelete();

            $table->unique([
                'import_batch_id',
                'row_hash',
            ]);

            $table->index([
                'import_batch_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
