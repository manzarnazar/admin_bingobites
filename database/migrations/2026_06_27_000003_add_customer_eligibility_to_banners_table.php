<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('banners', 'customer_eligibility')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->string('customer_eligibility', 20)->default('any');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('banners', 'customer_eligibility')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->dropColumn('customer_eligibility');
            });
        }
    }
};
