<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedSmallInteger('duration_days');
            // Informational for now: the most users the package allows. Nothing enforces it yet.
            $table->unsignedInteger('max_users');
            // Only active packages can be given to a teacher; existing subscriptions are unaffected.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One row per period a teacher was on a package. The latest ends_at is the current one.
        Schema::create('teacher_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // Set by subscriptions:expire when it suspended the account for this period.
            $table->timestamp('expired_handled_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'ends_at']);
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subscriptions');
        Schema::dropIfExists('packages');
    }
};
