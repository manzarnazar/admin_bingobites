<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            if (!Schema::hasColumn('coupons', 'visibility')) {
                $table->boolean('visibility')->default(1)->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('coupons')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};
