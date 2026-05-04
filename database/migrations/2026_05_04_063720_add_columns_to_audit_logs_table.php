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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->after('id');
            $table->string('action')->after('user_id');
            $table->string('model_type')->after('action');
            $table->unsignedBigInteger('model_id')->after('model_type');
            $table->text('description')->nullable()->after('model_id');
            $table->string('ip_address')->nullable()->after('description');
            $table->text('old_values')->nullable()->after('ip_address');
            $table->text('new_values')->nullable()->after('old_values');

            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropIndex('user_id');
            $table->dropIndex('action');
            $table->dropIndex('created_at');
            $table->dropColumn(['user_id', 'action', 'model_type', 'model_id', 'description', 'ip_address', 'old_values', 'new_values']);
        });
    }
};
