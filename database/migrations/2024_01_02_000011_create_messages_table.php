<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->string('role');
            $table->longText('content');
            $table->string('input_type')->nullable();
            $table->string('source_type')->nullable();
            $table->json('sources')->nullable();
            $table->json('metadata')->nullable();
            $table->string('model_name')->nullable();
            $table->unsignedInteger('token_usage_input')->nullable();
            $table->unsignedInteger('token_usage_output')->nullable();
            $table->json('safety_flags')->nullable();
            $table->timestamps();
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
