<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desknet_sync_log', function (Blueprint $table) {
            $table->id();
            $table->enum('sync_type', ['project_codes', 'staff', 'departments']);
            $table->enum('trigger_type', ['scheduled', 'manual']);
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->enum('status', ['running', 'success', 'failed']);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_deactivated')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();

            $table->foreign('triggered_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desknet_sync_log');
    }
};
