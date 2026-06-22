<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excel_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('timesheet_id')->nullable()->after('user_id');
            $table->foreign('timesheet_id')->references('id')->on('timesheets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('excel_uploads', function (Blueprint $table) {
            $table->dropForeign(['timesheet_id']);
            $table->dropColumn('timesheet_id');
        });
    }
};
