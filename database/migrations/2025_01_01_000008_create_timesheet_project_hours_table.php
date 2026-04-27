<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_project_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_row_id');
            $table->date('entry_date');
            $table->decimal('normal_nc_hours', 4, 1)->default(0.0);
            $table->decimal('normal_cobq_hours', 4, 1)->default(0.0);
            $table->decimal('ot_nc_hours', 4, 1)->default(0.0);
            $table->decimal('ot_cobq_hours', 4, 1)->default(0.0);
            $table->timestamps();

            $table->unique(['project_row_id', 'entry_date'], 'unique_project_day');
            $table->foreign('project_row_id')->references('id')->on('timesheet_project_rows')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_project_hours');
    }
};
