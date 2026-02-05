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
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
             $table->string('name'); // e.g., Laravel, Vue.js
            $table->string('category'); // frontend, backend, tools, databases
            $table->string('icon'); // Iconify icon name (e.g., mdi:laravel)
            $table->string('color')->nullable(); // Hex color for the skill
            $table->integer('proficiency')->default(80); // 0-100
            $table->integer('order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
