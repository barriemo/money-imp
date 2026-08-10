<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('source_type');
            $table->string('provider')->nullable();

            $table->uuid('bank_account_id')->nullable();

            $table->string('original_filename')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_hash')->nullable()->index();

            $table->string('status')->default('pending');

            $table->unsignedInteger('rows_seen')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);

            $table->json('metadata')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->foreignId('created_by')->nullable();

            $table->timestamps();

            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['source_type', 'status']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
