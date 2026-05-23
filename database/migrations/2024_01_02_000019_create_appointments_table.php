<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained('children')->nullOnDelete();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('appointment_type')->default('general_consultation');
            $table->string('booking_reference')->unique();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone')->default('UTC');
            $table->enum('status', ['pending_payment', 'confirmed', 'upcoming', 'completed', 'canceled', 'missed'])->default('pending_payment');
            $table->string('join_url')->nullable();
            $table->timestamp('join_available_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('specialist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
