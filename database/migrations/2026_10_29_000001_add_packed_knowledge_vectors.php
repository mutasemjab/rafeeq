<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_document_chunks', function (Blueprint $table): void {
            $table->binary('embedding_vector')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_document_chunks', fn (Blueprint $table) => $table->dropColumn('embedding_vector'));
    }
};
