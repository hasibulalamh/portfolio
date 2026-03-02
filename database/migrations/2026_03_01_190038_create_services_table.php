<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // Basic
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->text('short_description');
            $table->longText('detailed_description')->nullable();

            // Scope
            $table->enum('scope_type', ['Small', 'Medium', 'Large'])->nullable();
            $table->string('scope_description')->nullable();

            // Business
            $table->string('pricing_note')->nullable();
            $table->string('min_duration')->nullable();
            $table->string('max_duration')->nullable();

            // Tech
            $table->json('technologies')->nullable();

            // Control
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
