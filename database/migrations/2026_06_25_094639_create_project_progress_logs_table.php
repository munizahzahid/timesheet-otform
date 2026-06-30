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
        Schema::create('project_progress_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('phase_id')->nullable()->constrained('project_phases')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('log_type');
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_progress_logs');
    }
};
