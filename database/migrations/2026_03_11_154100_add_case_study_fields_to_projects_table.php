<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'problem_description')) {
                $table->text('problem_description')->nullable()->after('description');
            }
            if (!Schema::hasColumn('projects', 'problem_image')) {
                $table->string('problem_image')->nullable()->after('problem_description');
            }
            if (!Schema::hasColumn('projects', 'solution_description')) {
                $table->text('solution_description')->nullable()->after('problem_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['problem_description', 'problem_image', 'solution_description']);
        });
    }
};
