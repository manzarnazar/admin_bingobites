<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_platform', 16)->nullable()->after('cm_firebase_token');
        });

        Schema::table('guest_users', function (Blueprint $table) {
            $table->string('fcm_platform', 16)->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fcm_platform');
        });

        Schema::table('guest_users', function (Blueprint $table) {
            $table->dropColumn('fcm_platform');
        });
    }
};
