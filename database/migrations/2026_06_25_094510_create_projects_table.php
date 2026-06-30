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
        Schema::create('pm_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->nullable();
            $table->string('project_name');
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->date('start_date_plan')->nullable();
            $table->date('end_date_plan')->nullable();
            $table->date('start_date_actual')->nullable();
            $table->date('end_date_actual')->nullable();
            $table->date('start_date_revise')->nullable();
            $table->date('end_date_revise')->nullable();
            $table->integer('overall_plan_progress')->default(0);
            $table->integer('overall_actual_progress')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pm_projects');
    }
};
