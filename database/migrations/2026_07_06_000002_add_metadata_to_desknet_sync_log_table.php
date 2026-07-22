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
        Schema::table('desknet_sync_log', function (Blueprint $table) {
            if (!Schema::hasColumn('desknet_sync_log', 'metadata')) {
                $table->json('metadata')->nullable()->after('records_deactivated');
            }
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('desknet_sync_log', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
