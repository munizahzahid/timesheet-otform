<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_project_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->unsignedBigInteger('project_code_id');
            $table->string('project_name', 200)->nullable();
            $table->integer('row_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('timesheet_id')->references('id')->on('timesheets')->onDelete('cascade');
            $table->foreign('project_code_id')->references('id')->on('project_codes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_project_rows');
    }
};
