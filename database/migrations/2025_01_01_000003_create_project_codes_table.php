<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_codes', function (Blueprint $table) {
            $table->id();
            $table->string('desknet_id', 100)->unique()->nullable();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('client', 200)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('po_no', 100)->nullable();
            $table->string('project_manager', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->decimal('project_value', 15, 2)->nullable();
            $table->string('project_schedule_status', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_codes');
    }
};
