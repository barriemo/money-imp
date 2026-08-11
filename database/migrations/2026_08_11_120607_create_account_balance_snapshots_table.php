<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_balance_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('bank_account_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('balance', 14, 2);

            $table->string('source');
            $table->timestamp('balance_at');

            $table->boolean('verified')
                ->default(false);

            $table->unsignedTinyInteger('confidence')
                ->default(0);

            $table->text('notes')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            $table->index([
                'bank_account_id',
                'balance_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'account_balance_snapshots'
        );
    }
};
