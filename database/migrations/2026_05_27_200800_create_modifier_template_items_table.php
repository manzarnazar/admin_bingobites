<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('modifier_template_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('modifier_template_id');
            $table->unsignedBigInteger('add_on_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->tinyInteger('is_default')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();

            $table->index('modifier_template_id');
            $table->index('add_on_id');
            $table->unique(['modifier_template_id', 'add_on_id'], 'modifier_template_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('modifier_template_items');
    }
};
