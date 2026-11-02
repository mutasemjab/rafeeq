<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->boolean('show_attribution')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', fn (Blueprint $table) => $table->dropColumn('show_attribution'));
    }
};
