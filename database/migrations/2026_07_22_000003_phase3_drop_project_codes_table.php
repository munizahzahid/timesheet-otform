<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (final): Drop the now-obsolete project_codes table.
 *
 * After Phase 1 and Phase 2:
 *  - All project_codes data is in pm_projects
 *  - All FKs (timesheet_project_rows, ot_form_entries) have been remapped
 *  - No code writes to or reads from project_codes anymore
 */
return new class extends Migration
{
    public function up(): void
    {
        // Also drop the temporary rollback columns created during FK remap
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            if (Schema::hasColumn('timesheet_project_rows', 'old_project_code_id_tmp')) {
                $table->dropColumn('old_project_code_id_tmp');
            }
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            if (Schema::hasColumn('ot_form_entries', 'old_project_code_id_tmp')) {
                $table->dropColumn('old_project_code_id_tmp');
            }
        });

        Schema::dropIfExists('project_codes');
    }

    public function down(): void
    {
        // Recreate the table in a basic form. Data is lost after dropping.
        Schema::create('project_codes', function (Blueprint $table) {
            $table->id();
            $table->string('desknet_id', 100)->unique()->nullable();
            $table->string('code', 50)->unique();
            $table->string('name', 200)->nullable();
            $table->string('client', 200)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('po_no', 100)->nullable();
            $table->string('project_manager', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->decimal('project_value', 15, 2)->nullable();
            $table->string('project_schedule_status', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }
};
