<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->char('content_hash', 64)->nullable()->after('file_size');
            $table->text('source_path')->nullable()->after('content_hash');
            $table->json('ingestion_metadata')->nullable()->after('source_path');
            $table->boolean('index_only')->default(false)->after('ingestion_metadata');
            $table->unique('content_hash', 'knowledge_documents_content_hash_unique');
        });

        Schema::table('knowledge_document_chunks', function (Blueprint $table): void {
            $table->unique(
                ['knowledge_document_id', 'chunk_index'],
                'knowledge_chunks_document_index_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_document_chunks', function (Blueprint $table): void {
            $table->dropUnique('knowledge_chunks_document_index_unique');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropUnique('knowledge_documents_content_hash_unique');
            $table->dropColumn([
                'content_hash',
                'source_path',
                'ingestion_metadata',
                'index_only',
            ]);
        });
    }
};
