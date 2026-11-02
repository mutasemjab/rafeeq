<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->uuid('store_account_token')->nullable()->unique());
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->char('store_purchase_key', 64)->nullable()->unique();
            $t->text('store_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->dropUnique(['store_purchase_key']);
            $t->dropColumn(['store_purchase_key', 'store_reference']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['store_account_token']);
            $t->dropColumn('store_account_token');
        });
    }
};
