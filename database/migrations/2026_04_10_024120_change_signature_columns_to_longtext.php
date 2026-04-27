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
        Schema::table('timesheets', function (Blueprint $table) {
            $table->longText('staff_signature')->nullable()->change();
            $table->longText('l1_signature')->nullable()->change();
            $table->longText('l2_signature')->nullable()->change();
            $table->longText('l3_signature')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->text('staff_signature')->nullable()->change();
            $table->text('l1_signature')->nullable()->change();
            $table->text('l2_signature')->nullable()->change();
            $table->text('l3_signature')->nullable()->change();
        });
    }
};
