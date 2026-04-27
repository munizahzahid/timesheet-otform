<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert old statuses to new ones
        DB::table('ot_forms')->whereIn('status', [
            'pending_asst_mgr_pre', 'pending_hod_pre', 'pending_ceo_pre',
        ])->update(['status' => 'draft']);

        DB::table('ot_forms')->whereIn('status', [
            'pre_approved', 'pending_actual',
        ])->update(['status' => 'draft']);

        DB::table('ot_forms')->whereIn('status', [
            'pending_mgr_post', 'pending_hod_post',
        ])->update(['status' => 'draft']);

        DB::table('ot_forms')->whereIn('status', [
            'pending_ceo_post',
        ])->update(['status' => 'draft']);

        DB::table('ot_forms')->whereIn('status', [
            'rejected_pre', 'rejected_post',
        ])->update(['status' => 'draft']);

        // Simplify status: draft -> pending_hod -> approved (CEO optional)
        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft',
            'pending_hod',
            'approved',
            'pending_ceo',
            'ceo_approved',
            'rejected'
        ) DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft',
            'pending_asst_mgr_pre',
            'pending_hod_pre',
            'pending_ceo_pre',
            'pre_approved',
            'pending_actual',
            'pending_mgr_post',
            'pending_hod_post',
            'pending_ceo_post',
            'approved',
            'rejected_pre',
            'rejected_post'
        ) DEFAULT 'draft'");
    }
};
