<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_attachment_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_attachment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('page_number')->nullable();
            $table->string('section_title')->nullable();
            $table->longText('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->longText('embedding')->nullable();
            $table->unsignedInteger('embedding_dimensions')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('conversation_id');
            $table->index('chat_attachment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachment_chunks');
    }
};
