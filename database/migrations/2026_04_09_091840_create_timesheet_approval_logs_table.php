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
        Schema::create('timesheet_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('level', [0, 1, 2, 3]); // 0=Staff, 1=L1 (Asst Mgr), 2=L2 (Mgr/HOD), 3=L3 (DGM/CEO)
            $table->enum('action', ['submitted', 'approved', 'rejected']);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheet_approval_logs');
    }
};
