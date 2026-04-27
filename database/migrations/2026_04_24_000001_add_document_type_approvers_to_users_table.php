<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('timesheet_approver_id')->nullable()->after('reports_to');
            $table->foreign('timesheet_approver_id')->references('id')->on('users');

            $table->unsignedBigInteger('ot_exec_approver_id')->nullable()->after('timesheet_approver_id');
            $table->foreign('ot_exec_approver_id')->references('id')->on('users');

            $table->unsignedBigInteger('ot_exec_final_approver_id')->nullable()->after('ot_exec_approver_id');
            $table->foreign('ot_exec_final_approver_id')->references('id')->on('users');

            $table->unsignedBigInteger('ot_non_exec_approver_id')->nullable()->after('ot_exec_final_approver_id');
            $table->foreign('ot_non_exec_approver_id')->references('id')->on('users');

            $table->unsignedBigInteger('ot_non_exec_final_approver_id')->nullable()->after('ot_non_exec_approver_id');
            $table->foreign('ot_non_exec_final_approver_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['timesheet_approver_id']);
            $table->dropColumn('timesheet_approver_id');

            $table->dropForeign(['ot_exec_approver_id']);
            $table->dropColumn('ot_exec_approver_id');

            $table->dropForeign(['ot_exec_final_approver_id']);
            $table->dropColumn('ot_exec_final_approver_id');

            $table->dropForeign(['ot_non_exec_approver_id']);
            $table->dropColumn('ot_non_exec_approver_id');

            $table->dropForeign(['ot_non_exec_final_approver_id']);
            $table->dropColumn('ot_non_exec_final_approver_id');
        });
    }
};
