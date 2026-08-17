<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capability_definitions', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();

            $table->string('domain');

            $table->string('area');

            $table->string('owner')
                ->nullable();

            $table->text('purpose')
                ->nullable();

            $table->json('layers')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_definitions');
    }
};