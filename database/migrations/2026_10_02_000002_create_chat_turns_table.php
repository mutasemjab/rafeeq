<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->unique();
        });
        Schema::create('chat_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('client_message_id', 80);
            $table->string('request_hash', 64);
            $table->text('message');
            $table->string('language', 2)->default('en');
            $table->string('status', 20)->default('queued');
            $table->string('stage', 40)->default('queued');
            $table->unsignedInteger('attempt')->default(1);
            $table->foreignId('user_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'client_message_id']);
            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_turns');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['reply_to_message_id']);
            $table->dropColumn('reply_to_message_id');
        });
    }
};
