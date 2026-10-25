<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A teacher's own bot, created through the manager bot. The token is stored encrypted.
        Schema::create('teacher_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('telegram_bot_id');
            $table->string('username', 64);
            $table->text('token');
            $table->string('webhook_key', 40)->unique();
            $table->string('webhook_secret', 64);
            $table->timestamps();
        });

        // A teacher who asked for a bot: the code in the manager bot's start link ties the
        // Telegram account that opens it to this teacher, so the later "bot created" update
        // (which only names the creator) can be matched back.
        Schema::create('teacher_bot_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->unsignedBigInteger('telegram_user_id')->nullable()->index();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // chat_id is the parent's Telegram user id, the same in every bot, so a contact now also
        // says which bot it can be reached through (null = the shared platform bot).
        Schema::table('student_telegram_contacts', function (Blueprint $table) {
            $table->foreignId('teacher_bot_id')->nullable()->after('student_id')->constrained('teacher_bots')->cascadeOnDelete();
            $table->unique(['student_id', 'chat_id', 'teacher_bot_id'], 'contacts_student_chat_bot_unique');
        });

        Schema::table('student_telegram_contacts', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('student_telegram_contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_student_chat_bot_unique');
            $table->dropConstrainedForeignId('teacher_bot_id');
            $table->unique(['student_id', 'chat_id']);
        });

        Schema::dropIfExists('teacher_bot_requests');
        Schema::dropIfExists('teacher_bots');
    }
};
