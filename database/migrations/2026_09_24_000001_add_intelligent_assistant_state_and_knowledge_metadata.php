<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table): void {
            $table->string('avatar')->nullable()->after('name');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('active_domain', 100)->nullable()->after('summary');
            $table->json('case_state')->nullable()->after('active_domain');
            $table->text('next_question')->nullable()->after('case_state');
            $table->timestamp('last_planned_at')->nullable()->after('next_question');
            $table->index('active_domain');
        });

        Schema::table('child_memories', function (Blueprint $table): void {
            $table->string('memory_key', 160)->nullable()->after('type');
            $table->string('status', 32)->default('active')->after('content');
            $table->timestamp('last_confirmed_at')->nullable()->after('status');
            $table->index(['child_id', 'memory_key'], 'child_memories_child_key_index');
            $table->index(['child_id', 'status'], 'child_memories_child_status_index');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->json('topics')->nullable()->after('category');
            $table->json('problem_types')->nullable()->after('topics');
            $table->unsignedSmallInteger('age_min_months')->nullable()->after('problem_types');
            $table->unsignedSmallInteger('age_max_months')->nullable()->after('age_min_months');
            $table->string('audience', 100)->nullable()->after('age_max_months');
            $table->string('language', 12)->nullable()->after('audience');
            $table->string('evidence_level', 50)->nullable()->after('language');
            $table->string('publisher')->nullable()->after('evidence_level');
            $table->text('source_url')->nullable()->after('publisher');
            $table->date('published_at')->nullable()->after('source_url');
            $table->date('reviewed_at')->nullable()->after('published_at');
            $table->boolean('is_approved')->default(true)->after('reviewed_at');
            $table->index(['is_approved', 'status'], 'knowledge_documents_approval_status_index');
            $table->index(['age_min_months', 'age_max_months'], 'knowledge_documents_age_index');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropIndex('knowledge_documents_approval_status_index');
            $table->dropIndex('knowledge_documents_age_index');
            $table->dropColumn([
                'topics',
                'problem_types',
                'age_min_months',
                'age_max_months',
                'audience',
                'language',
                'evidence_level',
                'publisher',
                'source_url',
                'published_at',
                'reviewed_at',
                'is_approved',
            ]);
        });

        Schema::table('child_memories', function (Blueprint $table): void {
            $table->dropIndex('child_memories_child_key_index');
            $table->dropIndex('child_memories_child_status_index');
            $table->dropColumn(['memory_key', 'status', 'last_confirmed_at']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['active_domain']);
            $table->dropColumn(['active_domain', 'case_state', 'next_question', 'last_planned_at']);
        });

        Schema::table('children', function (Blueprint $table): void {
            $table->dropColumn('avatar');
        });
    }
};
