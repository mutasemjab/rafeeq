<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('birth_date')->nullable();
            $table->unsignedInteger('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('country_of_birth')->nullable();
            $table->string('country_of_residence')->nullable();
            $table->string('diagnosis')->nullable();
            $table->text('condition_notes')->nullable();
            $table->text('school_notes')->nullable();
            $table->text('therapy_notes')->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('communication_notes')->nullable();
            $table->text('behavior_notes')->nullable();
            $table->text('general_notes')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
