<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->boolean('jenis_ot_normal')->default(false)->after('ot_ph_hours');
            $table->boolean('jenis_ot_training')->default(false)->after('jenis_ot_normal');
            $table->boolean('jenis_ot_kaizen')->default(false)->after('jenis_ot_training');
            $table->boolean('jenis_ot_5s')->default(false)->after('jenis_ot_kaizen');
        });
    }

    public function down(): void
    {
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropColumn(['jenis_ot_normal', 'jenis_ot_training', 'jenis_ot_kaizen', 'jenis_ot_5s']);
        });
    }
};
