<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('month');
            $table->smallInteger('year');
            $table->string('company_name', 150)->nullable();
            $table->enum('status', [
                'draft',
                'pending_hod_pre',
                'pending_ceo_pre',
                'pre_approved',
                'pending_actual',
                'pending_hod_post',
                'approved',
                'rejected_pre',
                'rejected_post'
            ])->default('draft');
            $table->timestamp('plan_submitted_at')->nullable();
            $table->timestamp('actual_submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_forms');
    }
};
