<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ai_consent_accepted_at')->nullable()->after('last_login_at');
            $table->string('ai_consent_version', 32)->nullable()->after('ai_consent_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ai_consent_accepted_at',
                'ai_consent_version',
            ]);
        });
    }
};
