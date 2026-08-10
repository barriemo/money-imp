<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_aliases', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->uuid('supplier_id');

            $table->string('alias');
            $table->string('normalised_alias')->index();

            $table->string('source_type')->nullable();

            $table->decimal(
                'confidence',
                5,
                2
            )->default(100);

            $table->unsignedInteger(
                'successful_matches'
            )->default(0);

            $table->timestamp('last_matched_at')->nullable();

            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->cascadeOnDelete();

            $table->unique([
                'supplier_id',
                'normalised_alias',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_aliases');
    }
};
