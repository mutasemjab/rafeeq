<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_request_usages', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->index();
            $table->string('question_key', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('purpose', 24);
            $table->string('variant', 32);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('operation', 32);
            $table->string('stage', 100);
            $table->string('model', 100);
            $table->string('service_tier', 24)->nullable();
            $table->string('provider_request_id', 150)->nullable();
            $table->boolean('succeeded');
            $table->unsignedInteger('duration_ms');
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('web_search_calls')->default(0);
            $table->decimal('estimated_cost_usd', 14, 8)->nullable();
            $table->string('pricing_status', 40);
            $table->string('pricing_version', 40);
            $table->json('usage_details')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_usages');
    }
};
