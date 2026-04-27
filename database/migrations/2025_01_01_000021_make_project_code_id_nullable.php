<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('project_code_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('project_code_id')->nullable(false)->change();
        });
    }
};
