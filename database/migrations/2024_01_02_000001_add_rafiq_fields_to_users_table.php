<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->enum('role', ['user', 'specialist', 'admin'])->default('user')->after('last_name');
            $table->string('avatar')->nullable()->after('role');
            $table->enum('preferred_language', ['en', 'ar'])->default('en')->after('avatar');
            $table->enum('theme_preference', ['light', 'dark', 'system'])->default('system')->after('preferred_language');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active')->after('theme_preference');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->timestamp('last_login_at')->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'role', 'avatar',
                'preferred_language', 'theme_preference', 'status',
                'phone_verified_at', 'last_login_at',
            ]);
        });
    }
};
