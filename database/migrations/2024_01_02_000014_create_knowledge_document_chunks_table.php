<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('page_number')->nullable();
            $table->string('section_title')->nullable();
            $table->longText('content');
            $table->unsignedInteger('token_count')->nullable();
            $table->json('metadata')->nullable();
            $table->longText('embedding')->nullable();
            $table->unsignedInteger('embedding_dimensions')->nullable();
            $table->timestamps();
            $table->index('knowledge_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_document_chunks');
    }
};
