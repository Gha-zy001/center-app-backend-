<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->json('days');
            $table->string('time', 5);
            $table->timestamps();

            $table->index(['teacher_id', 'name']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20)->default('');
            $table->string('parent_phone', 20)->default('');
            // Opaque tokens: the QR code printed on the card and the parent's Telegram deep link.
            $table->string('qr_code', 64)->unique();
            $table->string('telegram_token', 64)->unique();
            $table->timestamps();

            $table->index(['teacher_id', 'group_id']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10);
            $table->timestamps();

            $table->unique(['student_id', 'group_id', 'date']);
            $table->index(['group_id', 'date']);
        });

        Schema::create('closed_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['group_id', 'date']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('student_groups')->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('max_score', 8, 2);
            $table->date('date');
            $table->timestamps();

            $table->index(['group_id', 'date']);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        foreach (['grades', 'exams', 'closed_sessions', 'attendance_records', 'students', 'student_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
