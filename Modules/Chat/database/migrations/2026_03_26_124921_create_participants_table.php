<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['conversation_id', 'user_id']); // Ensure a user is connected to a conversation only once
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
