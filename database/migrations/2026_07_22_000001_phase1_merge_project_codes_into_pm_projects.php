<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1: Merge project_codes data into pm_projects.
 *
 * This migration:
 *  1. Adds `is_active` and `old_project_code_id` columns to pm_projects.
 *  2. Copies every project_codes row into pm_projects:
 *     - If a pm_projects row already exists with the same code or desknet_id → update it
 *       (set is_active + old_project_code_id).
 *     - Otherwise → insert a new pm_projects row with mapped columns.
 *  3. Makes `project_code` NOT NULL and adds a unique index.
 *
 * The `old_project_code_id` column stores the original project_codes.id so that
 * Phase 3 can remap the FK columns (timesheet_project_rows.project_code_id,
 * ot_form_entries.project_code_id) from old IDs to new pm_projects.id.
 *
 * No FK changes are made in this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 0: Ensure pm_projects has all columns needed by project_codes data.
        // These columns were added locally by earlier project-module migrations, but
        // Coolify did not have them yet, so this migration must be self-contained.
        Schema::table('pm_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_projects', 'desknet_id')) {
                $table->string('desknet_id', 100)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('pm_projects', 'project_name')) {
                $table->string('project_name', 255)->nullable()->after('project_code');
            }
            if (!Schema::hasColumn('pm_projects', 'description')) {
                $table->text('description')->nullable()->after('project_name');
            }
            if (!Schema::hasColumn('pm_projects', 'start_date_plan')) {
                $table->date('start_date_plan')->nullable()->after('description');
            }
            if (!Schema::hasColumn('pm_projects', 'end_date_plan')) {
                $table->date('end_date_plan')->nullable()->after('start_date_plan');
            }
            if (!Schema::hasColumn('pm_projects', 'project_manager')) {
                $table->string('project_manager', 255)->nullable()->after('end_date_plan');
            }
            if (!Schema::hasColumn('pm_projects', 'po_no')) {
                $table->string('po_no', 100)->nullable()->after('project_manager');
            }
            if (!Schema::hasColumn('pm_projects', 'client')) {
                $table->string('client', 255)->nullable()->after('po_no');
            }
            if (!Schema::hasColumn('pm_projects', 'project_value')) {
                $table->decimal('project_value', 15, 2)->nullable()->after('client');
            }
            if (!Schema::hasColumn('pm_projects', 'project_schedule_status')) {
                $table->string('project_schedule_status', 100)->nullable()->after('project_value');
            }
            if (!Schema::hasColumn('pm_projects', 'year')) {
                $table->smallInteger('year')->nullable()->after('project_schedule_status');
            }
            if (!Schema::hasColumn('pm_projects', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('year');
            }
            if (!Schema::hasColumn('pm_projects', 'overall_plan_progress')) {
                $table->unsignedSmallInteger('overall_plan_progress')->default(0)->after('last_synced_at');
            }
            if (!Schema::hasColumn('pm_projects', 'overall_actual_progress')) {
                $table->unsignedSmallInteger('overall_actual_progress')->default(0)->after('overall_plan_progress');
            }
        });

        // Step 1: Add new columns (idempotent for Coolify re-run after partial failure)
        Schema::table('pm_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_projects', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
            if (!Schema::hasColumn('pm_projects', 'old_project_code_id')) {
                $table->unsignedBigInteger('old_project_code_id')->nullable()->after('id');
                $table->index('old_project_code_id');
            }
        });

        // Step 2: Migrate data from project_codes → pm_projects
        $projectCodes = DB::table('project_codes')->get();

        $merged = 0;
        $inserted = 0;

        foreach ($projectCodes as $pc) {
            // Try to find existing pm_projects row by desknet_id or project_code
            $existing = null;

            if ($pc->desknet_id) {
                $existing = DB::table('pm_projects')
                    ->where('desknet_id', $pc->desknet_id)
                    ->first();
            }

            if (!$existing && $pc->code) {
                $existing = DB::table('pm_projects')
                    ->where('project_code', $pc->code)
                    ->first();
            }

            if ($existing) {
                // Update existing pm_projects row: set is_active and old_project_code_id
                DB::table('pm_projects')
                    ->where('id', $existing->id)
                    ->update([
                        'old_project_code_id' => $pc->id,
                        'is_active' => $pc->is_active,
                    ]);
                $merged++;
            } else {
                // Insert new pm_projects row from project_codes data
                DB::table('pm_projects')->insert([
                    'old_project_code_id' => $pc->id,
                    'desknet_id' => $pc->desknet_id,
                    'project_code' => $pc->code,
                    'project_name' => $pc->name ?? $pc->code,
                    'description' => null,
                    'status' => $pc->is_active ? 'active' : 'inactive',
                    'is_active' => $pc->is_active,
                    'start_date_plan' => $pc->start_date,
                    'end_date_plan' => $pc->delivery_date,
                    'project_manager' => $pc->project_manager,
                    'po_no' => $pc->po_no,
                    'client' => $pc->client,
                    'project_value' => $pc->project_value,
                    'project_schedule_status' => $pc->project_schedule_status,
                    'year' => $pc->year,
                    'last_synced_at' => $pc->last_synced_at,
                    'overall_plan_progress' => 0,
                    'overall_actual_progress' => 0,
                    'created_at' => $pc->created_at ?? now(),
                    'updated_at' => $pc->updated_at ?? now(),
                ]);
                $inserted++;
            }
        }

        Log::info("Phase 1 migration complete: merged={$merged}, inserted={$inserted}, total_project_codes={$projectCodes->count()}");

        // Step 3: Verify no NULL project_code values remain before adding constraint
        $nullCount = DB::table('pm_projects')->whereNull('project_code')->count();
        if ($nullCount > 0) {
            // If any pm_projects row has NULL project_code (shouldn't happen after migration),
            // backfill with a placeholder to avoid breaking the NOT NULL constraint
            DB::table('pm_projects')
                ->whereNull('project_code')
                ->get()
                ->each(function ($row) {
                    DB::table('pm_projects')
                        ->where('id', $row->id)
                        ->update(['project_code' => 'UNKNOWN-' . $row->id]);
                });
            Log::warning("Phase 1: {$nullCount} pm_projects rows had NULL project_code — backfilled with placeholder.");
        }

        // Step 4: Make project_code NOT NULL and UNIQUE
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->string('project_code', 50)->nullable(false)->change();
            $table->unique('project_code');
        });
    }

    public function down(): void
    {
        // Remove unique index and revert nullable
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropUnique(['project_code']);
            $table->string('project_code', 255)->nullable()->change();
        });

        // Delete rows that were inserted from project_codes (those with old_project_code_id set
        // that were NOT originally in pm_projects)
        // We can identify these because they have old_project_code_id set and no phases/tasks.
        // For safety, just null out old_project_code_id and is_active on all rows.
        // Manual cleanup may be needed.

        // Remove all rows that were inserted (not merged)
        // Merged rows had old_project_code_id set on existing rows — we can't easily distinguish.
        // Safest rollback: just remove the added columns.

        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropIndex(['old_project_code_id']);
            $table->dropColumn(['is_active', 'old_project_code_id']);
        });
    }
};
