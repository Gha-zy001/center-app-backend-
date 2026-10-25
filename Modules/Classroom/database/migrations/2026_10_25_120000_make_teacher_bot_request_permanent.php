<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A teacher's start link no longer expires: it lives until the bot is created (null = never).
        Schema::table('teacher_bot_requests', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_bot_requests', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
