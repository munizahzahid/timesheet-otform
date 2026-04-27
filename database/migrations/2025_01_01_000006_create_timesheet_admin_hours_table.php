<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_admin_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->enum('admin_type', [
                'mc_leave', 'late', 'morning_assy', 'five_s',
                'ceramah_event', 'iso', 'training', 'admin_category'
            ]);
            $table->date('entry_date');
            $table->decimal('hours', 4, 1)->default(0.0);
            $table->timestamps();

            $table->unique(['timesheet_id', 'admin_type', 'entry_date']);
            $table->foreign('timesheet_id')->references('id')->on('timesheets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_admin_hours');
    }
};
