<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['person_profiles', 'children'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->timestamp('age_updated_at')->nullable());
        }
        Schema::create('person_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('category', 100)->default('other');
            $table->string('status', 20)->default('uploaded');
            $table->string('storage_disk', 20)->default('private');
            $table->boolean('has_legacy_public_copy')->default(false);
            $table->string('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('person_profile_id')->nullable()->constrained('person_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['person_profiles', 'children'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('age_updated_at'));
        }
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['person_profile_id']);
            $table->dropColumn('person_profile_id');
        });
        Schema::dropIfExists('person_documents');
    }
};
