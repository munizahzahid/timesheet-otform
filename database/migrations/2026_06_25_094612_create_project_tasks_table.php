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
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('project_phases')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('task_name');
            $table->integer('task_order');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('progress_plan')->default(0);
            $table->integer('progress_actual')->default(0);
            $table->integer('progress_revise')->nullable();
            $table->date('start_date_plan')->nullable();
            $table->date('end_date_plan')->nullable();
            $table->date('start_date_actual')->nullable();
            $table->date('end_date_actual')->nullable();
            $table->date('start_date_revise')->nullable();
            $table->date('end_date_revise')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
