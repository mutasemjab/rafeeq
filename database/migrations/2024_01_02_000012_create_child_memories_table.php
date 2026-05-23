<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('source')->nullable();
            $table->foreignId('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('child_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_memories');
    }
};
