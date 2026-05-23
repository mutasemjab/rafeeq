<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->date('available_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('slot_duration_minutes')->default(60);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('capacity')->default(1);
            $table->timestamps();
            $table->index(['specialist_id', 'available_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_availabilities');
    }
};
