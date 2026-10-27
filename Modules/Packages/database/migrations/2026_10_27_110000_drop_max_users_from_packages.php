<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A package's user count is not typed in: it is the number of teachers currently on it (computed).
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->default(0)->after('duration_days');
        });
    }
};
