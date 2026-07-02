<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('timesheet_hod_approver_id')->nullable()->after('timesheet_approver_id');
            $table->foreign('timesheet_hod_approver_id')->references('id')->on('users');

            $table->unsignedBigInteger('ot_approver_id')->nullable()->after('ot_non_exec_final_approver_id');
            $table->foreign('ot_approver_id')->references('id')->on('users');

            $table->unsignedBigInteger('ot_final_approver_id')->nullable()->after('ot_approver_id');
            $table->foreign('ot_final_approver_id')->references('id')->on('users');
        });

        // Seed new unified columns from existing exec approver columns
        DB::table('users')->update([
            'ot_approver_id' => DB::raw('COALESCE(ot_exec_approver_id, ot_non_exec_approver_id)'),
            'ot_final_approver_id' => DB::raw('COALESCE(ot_exec_final_approver_id, ot_non_exec_final_approver_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['timesheet_hod_approver_id']);
            $table->dropColumn('timesheet_hod_approver_id');

            $table->dropForeign(['ot_approver_id']);
            $table->dropColumn('ot_approver_id');

            $table->dropForeign(['ot_final_approver_id']);
            $table->dropColumn('ot_final_approver_id');
        });
    }
};
