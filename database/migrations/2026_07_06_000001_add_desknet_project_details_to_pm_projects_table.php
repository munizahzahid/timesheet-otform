<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_projects', 'desknet_id')) {
                $table->string('desknet_id', 100)->nullable()->after('id');
            }
            if (!Schema::hasColumn('pm_projects', 'project_manager')) {
                $table->string('project_manager', 200)->nullable()->after('project_name');
            }
            if (!Schema::hasColumn('pm_projects', 'project_manager_staff_id')) {
                $table->string('project_manager_staff_id', 50)->nullable()->after('project_manager');
            }
            if (!Schema::hasColumn('pm_projects', 'project_manager_department')) {
                $table->string('project_manager_department', 100)->nullable()->after('project_manager_staff_id');
            }
            if (!Schema::hasColumn('pm_projects', 'deskman_1')) {
                $table->string('deskman_1', 200)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'deskman_1_staff_id')) {
                $table->string('deskman_1_staff_id', 50)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'deskman_1_department')) {
                $table->string('deskman_1_department', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'deskman_2')) {
                $table->string('deskman_2', 200)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'deskman_2_staff_id')) {
                $table->string('deskman_2_staff_id', 50)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'deskman_2_department')) {
                $table->string('deskman_2_department', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'po_no')) {
                $table->string('po_no', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'client')) {
                $table->string('client', 200)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'attn')) {
                $table->string('attn', 200)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'full_address')) {
                $table->text('full_address')->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'tin')) {
                $table->string('tin', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'identification_no')) {
                $table->string('identification_no', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'contact_no')) {
                $table->string('contact_no', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'email')) {
                $table->string('email', 200)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'exemption_cert_no')) {
                $table->string('exemption_cert_no', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'term_1')) {
                $table->string('term_1', 255)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'term_2')) {
                $table->string('term_2', 255)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'term_3')) {
                $table->string('term_3', 255)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'term_4')) {
                $table->string('term_4', 255)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'term_5')) {
                $table->string('term_5', 255)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'project_value')) {
                $table->decimal('project_value', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'purchasing_budget_100')) {
                $table->decimal('purchasing_budget_100', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'purchasing_budget_95')) {
                $table->decimal('purchasing_budget_95', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'year')) {
                $table->smallInteger('year')->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'attachment_po_customer')) {
                $table->json('attachment_po_customer')->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'other_attachments')) {
                $table->json('other_attachments')->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'project_schedule_status')) {
                $table->string('project_schedule_status', 100)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropColumn([
                'desknet_id', 'project_manager', 'project_manager_staff_id', 'project_manager_department',
                'deskman_1', 'deskman_1_staff_id', 'deskman_1_department',
                'deskman_2', 'deskman_2_staff_id', 'deskman_2_department',
                'po_no', 'client', 'attn', 'full_address', 'tin', 'identification_no',
                'contact_no', 'email', 'exemption_cert_no',
                'term_1', 'term_2', 'term_3', 'term_4', 'term_5',
                'project_value', 'purchasing_budget_100', 'purchasing_budget_95',
                'year', 'attachment_po_customer', 'other_attachments',
                'project_schedule_status', 'last_synced_at',
            ]);
        });
    }
};
