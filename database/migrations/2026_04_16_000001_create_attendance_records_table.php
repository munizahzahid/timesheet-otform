<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('entry_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->default(0);
            $table->string('reason', 20)->nullable(); // PH, CAL, RES, ABS
            $table->string('day_type', 20)->default('working'); // working, off_day, public_holiday, mc
            $table->boolean('is_ot')->default(false);
            $table->decimal('ot_hours', 5, 2)->default(0);
            $table->time('ot_start_time')->nullable();
            $table->time('ot_end_time')->nullable();
            $table->string('ot_type', 20)->nullable(); // normal_day, rest_day, public_holiday
            $table->integer('month');
            $table->integer('year');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'entry_date']);
            $table->index(['user_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
