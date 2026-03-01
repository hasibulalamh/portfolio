<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('contact_messages', function (Blueprint $table) {
        $table->boolean('project_details_sent')->default(false)->after('status');
        $table->timestamp('project_details_sent_at')->nullable()->after('project_details_sent');
    });
}

public function down()
{
    Schema::table('contact_messages', function (Blueprint $table) {
        $table->dropColumn(['project_details_sent', 'project_details_sent_at']);
    });
}
};
