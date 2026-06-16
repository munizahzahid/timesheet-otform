<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add to timesheet_project_rows
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->string('project_category', 20)->nullable()->after('project_code_id');
            $table->string('manual_project_code_name', 255)->nullable()->after('project_category');
        });

        // Add to ot_form_entries
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->string('project_category', 20)->nullable()->after('project_code_id');
            $table->string('manual_project_code_name', 255)->nullable()->after('project_category');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->dropColumn(['project_category', 'manual_project_code_name']);
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropColumn(['project_category', 'manual_project_code_name']);
        });
    }
};
