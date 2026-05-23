<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->string('title')->nullable();
            $table->longText('summary')->nullable();
            $table->enum('source', ['text', 'voice'])->default('text');
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
            $table->index('child_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
