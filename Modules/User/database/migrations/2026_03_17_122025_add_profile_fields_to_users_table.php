<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->nullable();
            $table->string('phone')->nullable()->after('email');
            $table->enum('status', ['pending', 'active', 'rejected', 'suspended', 'banned'])->default('pending');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('locale')->default('en')->after('last_login_at');
            $table->string('timezone')->default('UTC')->after('locale');
            $table->json('preferences')->nullable()->after('timezone');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'phone',  'status', 
                'last_login_at', 'locale', 'timezone', 
                'preferences', 'deleted_at'
            ]);
        });
    }
};
