<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->string('image', 100)->nullable();
            $table->string('type', 50)->default('bogo');
            $table->unsignedTinyInteger('discount_cheapest_percent')->default(0);
            $table->unsignedTinyInteger('discount_expensive_percent')->default(100);
            $table->boolean('charge_modifier_addons')->default(true);
            $table->string('customer_type', 20)->default('any');
            $table->string('order_type', 20)->default('any');
            $table->boolean('once_per_customer')->default(false);
            $table->boolean('is_exclusive')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('highlight_group')->default(1);
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
