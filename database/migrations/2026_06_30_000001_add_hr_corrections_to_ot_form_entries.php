<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->json('hr_corrections')->nullable()->after('remarks')->comment('Snapshot of original values before HR edit');
        });

        Schema::table('ot_forms', function (Blueprint $table) {
            $table->text('hr_remarks')->nullable()->after('status')->comment('Summary of HR corrections');
            $table->timestamp('hr_edited_at')->nullable()->after('hr_remarks');
            $table->foreignId('hr_edited_by')->nullable()->after('hr_edited_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ot_form_entries', function (Blueprint $table) {
            $table->dropColumn('hr_corrections');
        });

        Schema::table('ot_forms', function (Blueprint $table) {
            $table->dropForeign(['hr_edited_by']);
            $table->dropColumn(['hr_remarks', 'hr_edited_at', 'hr_edited_by']);
        });
    }
};
