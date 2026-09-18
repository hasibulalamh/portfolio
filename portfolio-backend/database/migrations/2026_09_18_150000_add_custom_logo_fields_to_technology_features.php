<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['skills', 'api_showcases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('logo_type')->default('library')->after('icon_slug');
                $table->string('logo_url')->nullable()->after('logo_type');
            });
        }
    }

    public function down(): void
    {
        foreach (['skills', 'api_showcases'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['logo_type', 'logo_url']);
            });
        }
    }
};
