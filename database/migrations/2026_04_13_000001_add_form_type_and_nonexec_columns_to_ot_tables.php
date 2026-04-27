<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- ot_forms: add form_type, section_line, new statuses, total_ot_hours ---
        Schema::table('ot_forms', function (Blueprint $table) {
            $table->string('form_type', 20)->default('executive')->after('year');
            $table->string('section_line', 150)->nullable()->after('company_name');
            $table->decimal('total_ot_hours', 6, 2)->default(0.00)->after('actual_submitted_at');
        });

        // Alter status ENUM to include new values for non-executive workflow
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

        // --- ot_form_entries: make some columns nullable, add non-exec columns ---
        Schema::table('ot_form_entries', function (Blueprint $table) {
            // Non-executive only columns
            $table->boolean('meal_break')->default(false)->after('ot_ph_hours');
            $table->boolean('over_3_hours')->default(false)->after('meal_break');
            $table->boolean('is_shift')->default(false)->after('over_3_hours');
            $table->string('ot_type', 20)->nullable()->after('is_shift');
            $table->decimal('ot_rate_1', 5, 2)->default(0.00)->after('ot_type');
            $table->decimal('ot_rate_2', 5, 2)->default(0.00)->after('ot_rate_1');
            $table->decimal('ot_rate_3', 5, 2)->default(0.00)->after('ot_rate_2');
            $table->decimal('ot_rate_4', 5, 2)->default(0.00)->after('ot_rate_3');
            $table->decimal('ot_rate_5', 5, 2)->default(0.00)->after('ot_rate_4');
            $table->text('remarks')->nullable()->after('ot_rate_5');
        });

        // Make project_code_id and project_name nullable (non-exec rows with no OT)
        DB::statement("ALTER TABLE ot_form_entries MODIFY COLUMN project_code_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE ot_form_entries MODIFY COLUMN project_name VARCHAR(200) NULL");
        DB::statement("ALTER TABLE ot_form_entries MODIFY COLUMN planned_start_time TIME NULL");
        DB::statement("ALTER TABLE ot_form_entries MODIFY COLUMN planned_end_time TIME NULL");
    }

    public function down(): void
    {
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropColumn([
                'meal_break', 'over_3_hours', 'is_shift', 'ot_type',
                'ot_rate_1', 'ot_rate_2', 'ot_rate_3', 'ot_rate_4', 'ot_rate_5',
                'remarks',
            ]);
        });

        Schema::table('ot_forms', function (Blueprint $table) {
            $table->dropColumn(['form_type', 'section_line', 'total_ot_hours']);
        });

        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft','pending_hod_pre','pending_ceo_pre','pre_approved',
            'pending_actual','pending_hod_post','approved','rejected_pre','rejected_post'
        ) DEFAULT 'draft'");
    }
};
