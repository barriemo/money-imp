<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('type');

            $table->string('name');

            $table->decimal(
                'amount',
                14,
                2
            );

            $table->date('due_date')
                ->nullable();

            $table->string('status')
                ->default('open');

            $table->string('source')
                ->nullable();

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
                'type',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'liabilities'
        );
    }
};
