<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (student, Telegram account): a parent with two children has two rows,
        // and a child can have a father and a mother linked.
        Schema::create('student_telegram_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id', 32);
            $table->string('name');
            $table->timestamps();

            $table->unique(['student_id', 'chat_id']);
            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_telegram_contacts');
    }
};
