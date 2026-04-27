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
        // Add pending_manager and pending_gm statuses to ot_forms table
        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft',
            'pending_manager',
            'pending_gm',
            'approved',
            'rejected'
        ) DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous status enum
        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft',
            'pending_hod',
            'approved',
            'pending_ceo',
            'ceo_approved',
            'rejected'
        ) DEFAULT 'draft'");
    }
};
