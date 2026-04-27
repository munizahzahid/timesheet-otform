<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 50);
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->tinyInteger('level');
            $table->enum('phase', ['timesheet', 'ot_pre', 'ot_post'])->default('timesheet');
            $table->enum('action', ['approved', 'rejected']);
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->useCurrent();

            $table->index(['approvable_type', 'approvable_id']);
            $table->foreign('approver_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
