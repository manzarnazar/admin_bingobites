<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->unsignedBigInteger('promotion_id')->nullable()->after('category_id');
            $table->string('headline')->nullable()->after('title');
            $table->string('cta_label', 50)->nullable()->default('Order Now')->after('headline');
            $table->string('link_type', 20)->nullable()->default('none')->after('cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['promotion_id', 'headline', 'cta_label', 'link_type']);
        });
    }
};
