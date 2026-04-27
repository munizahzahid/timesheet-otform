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
        // Add HOD signature columns
        Schema::table('timesheets', function (Blueprint $table) {
            $table->string('hod_signature')->nullable()->after('staff_signed_at');
            $table->timestamp('hod_signed_at')->nullable()->after('hod_signature');
        });

        // Add pending_hod and rejected_hod statuses to timesheets table
        DB::statement("ALTER TABLE timesheets MODIFY COLUMN status ENUM(
            'draft',
            'pending_hod',
            'pending_l1',
            'pending_l2',
            'pending_l3',
            'approved',
            'rejected_hod',
            'rejected_l1',
            'rejected_l2',
            'rejected_l3',
            'rejected'
        ) DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove HOD signature columns
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn(['hod_signature', 'hod_signed_at']);
        });

        // Revert to previous status enum
        DB::statement("ALTER TABLE timesheets MODIFY COLUMN status ENUM(
            'draft',
            'pending_l1',
            'pending_l2',
            'pending_l3',
            'approved',
            'rejected_l1',
            'rejected_l2',
            'rejected_l3',
            'rejected'
        ) DEFAULT 'draft'");
    }
};
