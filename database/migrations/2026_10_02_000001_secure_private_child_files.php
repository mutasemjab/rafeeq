<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['chat_attachments', 'child_documents'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                // Existing files remain readable until copied by the backfill.
                $table->string('storage_disk', 32)->default('public');
                $table->boolean('has_legacy_public_copy')->default(false);
            });
        }
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE child_documents MODIFY status ENUM('uploaded','processing','processed','failed') NOT NULL DEFAULT 'uploaded'");
        } else {
            Schema::table('child_documents', fn (Blueprint $table) => $table->string('status')->default('uploaded')->change());
        }
    }

    public function down(): void
    {
        foreach (['chat_attachments', 'child_documents'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['storage_disk', 'has_legacy_public_copy']));
        }
    }
};
