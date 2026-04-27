<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('morning_assembly_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('assembly_date');
            $table->boolean('attended')->default(false);
            $table->string('source', 50)->default('google_form');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'assembly_date']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('morning_assembly_log');
    }
};
