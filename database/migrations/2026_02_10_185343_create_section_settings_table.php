<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('section_settings', function (Blueprint $table) {
            $table->id();
             $table->string('model_name')->unique();
            $table->string('section_name');
            $table->string('component_name');
            $table->string('section_id');
            $table->boolean('is_enabled')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_settings');
    }
};
