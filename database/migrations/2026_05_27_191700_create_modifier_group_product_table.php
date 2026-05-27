<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifier_group_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('selection_type', ['single', 'multi'])->nullable();
            $table->unsignedInteger('min')->nullable();
            $table->unsignedInteger('max')->nullable();
            $table->boolean('is_required')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_default_enabled')->default(true);
            $table->timestamps();

            $table->unique(['modifier_group_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifier_group_product');
    }
};
