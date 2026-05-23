<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['free', 'pro'])->default('free');
            $table->enum('billing_period', ['monthly', 'yearly', 'none'])->default('none');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('ai_daily_limit')->nullable();
            $table->unsignedInteger('child_limit')->nullable();
            $table->boolean('has_voice_mode')->default(false);
            $table->boolean('has_advanced_ai')->default(false);
            $table->boolean('has_evaluation_reports')->default(false);
            $table->boolean('has_priority_support')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
