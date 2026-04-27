<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_form_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ot_form_id');
            $table->date('entry_date');
            $table->unsignedBigInteger('project_code_id');
            $table->string('project_name', 200);
            // Section (A): Plan
            $table->time('planned_start_time');
            $table->time('planned_end_time');
            $table->decimal('planned_total_hours', 5, 2)->default(0.00);
            // Section (C): Actual (nullable — filled after OT)
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->decimal('actual_total_hours', 5, 2)->default(0.00);
            // Day-type breakdown for actual OT hours
            $table->decimal('ot_normal_day_hours', 5, 2)->default(0.00);
            $table->decimal('ot_rest_day_hours', 5, 2)->default(0.00);
            $table->decimal('ot_ph_hours', 5, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('ot_form_id')->references('id')->on('ot_forms')->onDelete('cascade');
            $table->foreign('project_code_id')->references('id')->on('project_codes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_form_entries');
    }
};
