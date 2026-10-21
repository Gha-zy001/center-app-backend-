<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['user_id', 'read_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });
    }
};
