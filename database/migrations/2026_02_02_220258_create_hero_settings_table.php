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
        Schema::create('hero_settings', function (Blueprint $table) {
            $table->id();
             $table->string('name');
            $table->string('tagline');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->string('resume_url')->nullable();
            $table->string('primary_cta_text')->default('Hire Me');
            $table->string('primary_cta_url')->default('#contact');
            $table->string('secondary_cta_text')->default('View Resume');
            $table->string('secondary_cta_url')->default('#resume');
            $table->string('resume_file')->nullable();
            $table->boolean('show_photo')->default(true);
            $table->boolean('show_social_links')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_settings');
    }
};
