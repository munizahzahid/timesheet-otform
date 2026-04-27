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
        Schema::table('timesheets', function (Blueprint $table) {
            // Digital signatures (base64 encoded image data)
            $table->text('staff_signature')->nullable();
            $table->text('l1_signature')->nullable(); // Asst Mgr
            $table->text('l2_signature')->nullable(); // Mgr/HOD
            $table->text('l3_signature')->nullable(); // DGM/CEO
            
            // Signature timestamps
            $table->timestamp('staff_signed_at')->nullable();
            $table->timestamp('l1_signed_at')->nullable();
            $table->timestamp('l2_signed_at')->nullable();
            $table->timestamp('l3_signed_at')->nullable();
            
            // Rejection remarks
            $table->text('rejection_remarks')->nullable();
        });
        
        // Update status enum to include new values
        DB::statement("ALTER TABLE timesheets MODIFY COLUMN status ENUM('draft', 'pending_l1', 'pending_l2', 'pending_l3', 'approved', 'rejected_l1', 'rejected_l2', 'rejected_l3', 'rejected') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn([
                'staff_signature',
                'l1_signature',
                'l2_signature',
                'l3_signature',
                'staff_signed_at',
                'l1_signed_at',
                'l2_signed_at',
                'l3_signed_at',
                'rejection_remarks',
            ]);
        });
        
        // Revert status enum to original values
        DB::statement("ALTER TABLE timesheets MODIFY COLUMN status ENUM('draft', 'pending_l1', 'pending_l2', 'approved', 'rejected') DEFAULT 'draft'");
    }
};
