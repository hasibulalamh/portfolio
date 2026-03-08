<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform');        // GitHub, LinkedIn, Facebook etc.
            $table->string('url');             // https://github.com/...
            $table->string('icon')->nullable(); // mdi:github, mdi:linkedin etc.
            $table->string('color')->nullable(); // #1DA1F2 etc.
            $table->integer('order')->default(0);
            $table->boolean('show_in_hero')->default(true);
            $table->boolean('show_in_contact')->default(true);
            $table->boolean('show_in_footer')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
