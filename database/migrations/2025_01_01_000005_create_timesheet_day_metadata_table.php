<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_day_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->date('entry_date');
            $table->enum('day_of_week', ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN']);
            $table->enum('day_type', ['working', 'off_day', 'public_holiday', 'mc', 'leave'])->default('working');
            $table->decimal('available_hours', 4, 1)->default(8.0);
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('late_hours', 4, 1)->default(0.0);
            $table->decimal('ot_eligible_hours', 4, 1)->default(0.0);
            $table->decimal('attendance_hours', 4, 1)->default(0.0);
            $table->timestamps();

            $table->unique(['timesheet_id', 'entry_date']);
            $table->foreign('timesheet_id')->references('id')->on('timesheets')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_day_metadata');
    }
};
