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
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
                        $table->string('type'); // work, education, certification
            $table->string('title'); // Job title or Degree
            $table->string('company'); // Company or Institution name
            $table->string('company_logo')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = current
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->json('responsibilities')->nullable(); // Array of bullet points
            $table->json('technologies')->nullable(); // Skills/tools used
            $table->json('achievements')->nullable(); // Key accomplishments
            $table->string('website_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
