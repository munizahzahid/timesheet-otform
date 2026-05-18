<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE timesheet_day_metadata MODIFY COLUMN day_type ENUM('working', 'off_day', 'public_holiday', 'mc', 'leave', 'absent') DEFAULT 'working'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE timesheet_day_metadata MODIFY COLUMN day_type ENUM('working', 'off_day', 'public_holiday', 'mc', 'leave') DEFAULT 'working'");
    }
};
