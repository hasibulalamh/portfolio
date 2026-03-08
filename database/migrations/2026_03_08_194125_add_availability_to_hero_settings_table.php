<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_settings', function (Blueprint $table) {
            $table->boolean('show_availability')->default(true)->after('show_social_links');
            $table->boolean('is_available')->default(true)->after('show_availability');
            $table->string('availability_text')->default('Available for hire')->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('hero_settings', function (Blueprint $table) {
            $table->dropColumn(['show_availability', 'is_available', 'availability_text']);
        });
    }
};
