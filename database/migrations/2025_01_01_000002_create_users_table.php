<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('desknet_id', 100)->unique()->nullable();
            $table->string('staff_no', 20)->unique()->nullable();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->enum('role', ['staff', 'admin', 'assistant_manager', 'manager_hod', 'ceo'])->default('staff');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('reports_to')->nullable();
            $table->string('designation', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->rememberToken();

            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('reports_to')->references('id')->on('users');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
