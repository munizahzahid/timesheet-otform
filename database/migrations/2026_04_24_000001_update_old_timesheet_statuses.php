<?php

use App\Models\Timesheet;
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
        // Update pending_l2 and pending_l3 to pending_l1 since CEO/DGM level was removed
        Timesheet::whereIn('status', ['pending_l2', 'pending_l3'])
            ->update(['status' => 'pending_l1']);

        // Update rejected_l2 and rejected_l3 to rejected_l1
        Timesheet::whereIn('status', ['rejected_l2', 'rejected_l3'])
            ->update(['status' => 'rejected_l1']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed without knowing the original state
        // For simplicity, we'll just leave it as is
    }
};
