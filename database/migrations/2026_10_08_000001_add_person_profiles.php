<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->string('display_name', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('age_months')->nullable();
            $table->string('relationship', 20);
            $table->string('preferred_language', 8)->default('ar');
            $table->string('communication_preferences', 1000)->nullable();
            $table->string('reported_diagnosis', 1000)->nullable();
            $table->string('diagnosis_source', 30)->nullable();
            $table->timestamp('permission_attested_at');
            $table->timestamp('ai_consent_accepted_at')->nullable();
            $table->timestamp('persistence_consent_accepted_at');
            $table->string('consent_version', 32);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'legacy_child_id']);
        });
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('person_profile_id')->nullable()->constrained('person_profiles')->restrictOnDelete();
        });
        Schema::create('person_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('memory_key', 160);
            $table->string('type', 30);
            $table->string('title', 160);
            $table->text('content');
            $table->string('status', 20)->default('active');
            $table->float('confidence');
            $table->string('source', 40);
            $table->timestamp('last_confirmed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['person_profile_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_memories');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['person_profile_id']);
            $table->dropColumn('person_profile_id');
        });
        Schema::dropIfExists('person_profiles');
    }
};
