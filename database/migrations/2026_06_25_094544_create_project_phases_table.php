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
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->string('phase_name');
            $table->integer('phase_order');
            $table->date('start_date_plan')->nullable();
            $table->date('end_date_plan')->nullable();
            $table->date('start_date_actual')->nullable();
            $table->date('end_date_actual')->nullable();
            $table->date('start_date_revise')->nullable();
            $table->date('end_date_revise')->nullable();
            $table->integer('progress_plan')->default(0);
            $table->integer('progress_actual')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_phases');
    }
};
