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
        Schema::table('pm_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_projects', 'date_time_added')) {
                $table->timestamp('date_time_added')->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'added_by')) {
                $table->string('added_by', 200)->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'date_time_updated')) {
                $table->timestamp('date_time_updated')->nullable();
            }
            if (!Schema::hasColumn('pm_projects', 'updated_by')) {
                $table->string('updated_by', 200)->nullable();
            }
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->dropColumn(['date_time_added', 'added_by', 'date_time_updated', 'updated_by']);
        });
    }
};
