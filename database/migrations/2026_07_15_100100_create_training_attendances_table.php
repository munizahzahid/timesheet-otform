<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('staff_no', 50);
            $table->text('signature');
            $table->timestamp('attended_at');
            $table->timestamps();

            $table->unique(['training_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_attendances');
    }
};
