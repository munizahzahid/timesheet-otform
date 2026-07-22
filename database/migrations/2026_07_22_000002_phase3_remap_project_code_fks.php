<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 3 (step 1): Remap foreign keys from project_codes.id to pm_projects.id.
 *
 * This migration:
 *  1. Adds temporary columns to hold the OLD project_codes FK values.
 *  2. Updates project_code_id in timesheet_project_rows and ot_form_entries to the
 *     corresponding pm_projects.id using pm_projects.old_project_code_id.
 *  3. Drops the old FK constraints and re-adds them pointing to pm_projects(id).
 *  4. Keeps temp columns so the migration is reversible.
 *
 * After this migration:
 *  - All project_code_id values reference pm_projects.id.
 *  - The projectCode() relations can be switched to Project model.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add temp columns to preserve old IDs for rollback safety (idempotent)
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('timesheet_project_rows', 'old_project_code_id_tmp')) {
                $table->unsignedBigInteger('old_project_code_id_tmp')->nullable()->after('project_code_id');
            }
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('ot_form_entries', 'old_project_code_id_tmp')) {
                $table->unsignedBigInteger('old_project_code_id_tmp')->nullable()->after('project_code_id');
            }
        });

        // Step 2: Backup old values (safe to re-run)
        DB::statement('UPDATE timesheet_project_rows SET old_project_code_id_tmp = project_code_id WHERE old_project_code_id_tmp IS NULL OR old_project_code_id_tmp != project_code_id');
        DB::statement('UPDATE ot_form_entries SET old_project_code_id_tmp = project_code_id WHERE old_project_code_id_tmp IS NULL OR old_project_code_id_tmp != project_code_id');

        // Step 3: Drop old FK constraints so we can freely update project_code_id values
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->dropForeign(['project_code_id']);
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropForeign(['project_code_id']);
        });

        // Step 4: Remap to pm_projects.id
        DB::statement('
            UPDATE timesheet_project_rows tpr
            INNER JOIN pm_projects pm ON pm.old_project_code_id = tpr.project_code_id
            SET tpr.project_code_id = pm.id
            WHERE tpr.project_code_id IS NOT NULL
        ');

        DB::statement('
            UPDATE ot_form_entries ofe
            INNER JOIN pm_projects pm ON pm.old_project_code_id = ofe.project_code_id
            SET ofe.project_code_id = pm.id
            WHERE ofe.project_code_id IS NOT NULL
        ');

        // Step 5: Verify no orphan references before adding the new FK
        $tsOrphans = DB::table('timesheet_project_rows as tpr')
            ->leftJoin('pm_projects as pm', 'pm.id', '=', 'tpr.project_code_id')
            ->whereNotNull('tpr.project_code_id')
            ->whereNull('pm.id')
            ->count();

        $otOrphans = DB::table('ot_form_entries as ofe')
            ->leftJoin('pm_projects as pm', 'pm.id', '=', 'ofe.project_code_id')
            ->whereNotNull('ofe.project_code_id')
            ->whereNull('pm.id')
            ->count();

        if ($tsOrphans > 0 || $otOrphans > 0) {
            throw new \RuntimeException(
                "FK remapping produced orphans: timesheet={$tsOrphans}, ot={$otOrphans}. " .
                "Rollback this migration immediately and investigate."
            );
        }

        // Step 6: Add new FK constraints pointing to pm_projects
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->foreign('project_code_id')->references('id')->on('pm_projects')->onDelete('set null');
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->foreign('project_code_id')->references('id')->on('pm_projects')->onDelete('set null');
        });

        Log::info('Phase 3 step 1 complete: project_code_id FKs remapped to pm_projects.id');
    }

    public function down(): void
    {
        // Step 1: Drop new FK constraints and re-add to project_codes
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->dropForeign(['project_code_id']);
            $table->foreign('project_code_id')->references('id')->on('project_codes');
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropForeign(['project_code_id']);
            $table->foreign('project_code_id')->references('id')->on('project_codes');
        });

        // Step 2: Restore old values from temp columns
        DB::statement('UPDATE timesheet_project_rows SET project_code_id = old_project_code_id_tmp WHERE old_project_code_id_tmp IS NOT NULL');
        DB::statement('UPDATE ot_form_entries SET project_code_id = old_project_code_id_tmp WHERE old_project_code_id_tmp IS NOT NULL');

        // Step 3: Drop temp columns
        Schema::table('timesheet_project_rows', function (Blueprint $table) {
            $table->dropColumn('old_project_code_id_tmp');
        });

        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropColumn('old_project_code_id_tmp');
        });
    }
};
