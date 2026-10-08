<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_temporary')->default(false);
            $table->timestamp('expires_at')->nullable()->index();
            $table->json('temporary_subject')->nullable();
        });
        Schema::create('chat_usage_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('used')->default(0);
            $table->unique(['user_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_usage_adjustments');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['is_temporary', 'expires_at', 'temporary_subject']);
        });
    }
};
