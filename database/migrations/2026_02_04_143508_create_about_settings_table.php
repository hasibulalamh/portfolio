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
        Schema::create('about_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('About Me');
            $table->string('subtitle')->nullable();
            $table->text('bio');
            $table->string('image')->nullable();
            $table->integer('years_experience')->default(0);
            $table->integer('projects_completed')->default(0);
            $table->integer('happy_clients')->default(0);
            $table->integer('technologies_count')->default(0);
            $table->json('highlights')->nullable(); // Key achievements
            $table->boolean('show_image')->default(true);
            $table->boolean('show_stats')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_settings');
    }
};
