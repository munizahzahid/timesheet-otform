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
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->decimal('ot_rest_day_excess_hours', 5, 2)->default(0)->after('ot_rest_day_hours');
            $table->integer('ot_rest_day_count')->default(0)->after('ot_rest_day_excess_hours');
            $table->boolean('is_public_holiday')->default(false)->after('ot_ph_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropColumn(['ot_rest_day_excess_hours', 'ot_rest_day_count', 'is_public_holiday']);
        });
    }
};
