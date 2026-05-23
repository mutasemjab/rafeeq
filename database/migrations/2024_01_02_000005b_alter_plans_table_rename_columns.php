<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Rename existing columns to match application code
            $table->renameColumn('ai_daily_limit', 'ai_messages_per_day');
            $table->renameColumn('child_limit', 'max_children');
            $table->renameColumn('has_advanced_ai', 'has_specialist_access');
            $table->renameColumn('has_evaluation_reports', 'has_progress_reports');

            // Add missing columns
            $table->unsignedInteger('max_documents_per_child')->nullable()->after('max_children');

            // billing_period needs 'lifetime' option — update enum
            $table->enum('billing_period', ['monthly', 'yearly', 'lifetime', 'none'])
                  ->default('none')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('ai_messages_per_day', 'ai_daily_limit');
            $table->renameColumn('max_children', 'child_limit');
            $table->renameColumn('has_specialist_access', 'has_advanced_ai');
            $table->renameColumn('has_progress_reports', 'has_evaluation_reports');
            $table->dropColumn('max_documents_per_child');
        });
    }
};
