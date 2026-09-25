<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')
            ->where('slug', 'free')
            ->update([
                'ai_messages_per_day' => 100,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('plans')
            ->where('slug', 'free')
            ->where('ai_messages_per_day', 100)
            ->update([
                'ai_messages_per_day' => 5,
                'updated_at' => now(),
            ]);
    }
};
