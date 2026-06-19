<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add pending_hr and returned_hr statuses to ot_forms table
        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft',
            'pending_manager',
            'pending_hr',
            'pending_gm',
            'approved',
            'rejected',
            'returned_hr'
        ) DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Revert to previous status enum
        DB::statement("ALTER TABLE ot_forms MODIFY COLUMN status ENUM(
            'draft',
            'pending_manager',
            'pending_gm',
            'approved',
            'rejected'
        ) DEFAULT 'draft'");
    }
};
