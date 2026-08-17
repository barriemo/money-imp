<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capability_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId(
                'capability_definition_id'
            )
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->text(
                'description'
            )
                ->nullable();

            $table->unsignedInteger(
                'priority'
            )
                ->default(50);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_actions');
    }
};
