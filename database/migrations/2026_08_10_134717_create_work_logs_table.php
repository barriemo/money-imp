<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('client_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->text('description');

            $table->unsignedInteger('minutes');

            $table->date('performed_at');

            $table->string('billing_hint')
                ->default('unsure');

            $table->string('commercial_status')
                ->default('unreviewed');

            $table->decimal(
                'rate_snapshot',
                10,
                2
            )->nullable();

            $table->decimal(
                'commercial_value',
                10,
                2
            )->nullable();

            $table->text('commercial_notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'commercial_status',
                'performed_at',
            ]);

            $table->index([
                'client_id',
                'performed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
