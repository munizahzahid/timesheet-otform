<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add hr_forwarded and hr_returned actions to approval_logs table
        DB::statement("ALTER TABLE approval_logs MODIFY COLUMN action ENUM(
            'approved',
            'rejected',
            'hr_forwarded',
            'hr_returned'
        ) DEFAULT 'approved'");
    }

    public function down(): void
    {
        // Revert to previous action enum
        DB::statement("ALTER TABLE approval_logs MODIFY COLUMN action ENUM(
            'approved',
            'rejected'
        ) DEFAULT 'approved'");
    }
};
