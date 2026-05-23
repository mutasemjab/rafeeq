<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialists', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->string('title')->nullable();
            $table->string('specialty');
            $table->text('bio')->nullable();
            $table->unsignedInteger('years_of_experience')->default(0);
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('avatar')->nullable();
            $table->enum('whatsapp_join_mode', ['manual_link', 'direct_number'])->default('manual_link');
            $table->string('whatsapp_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['specialty', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialists');
    }
};
