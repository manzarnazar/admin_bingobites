<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_group_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_item_group_id')->constrained('promotion_item_groups')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->timestamps();

            $table->unique(['promotion_item_group_id', 'product_id'], 'promo_group_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_group_products');
    }
};
