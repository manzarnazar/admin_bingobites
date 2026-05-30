<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('add_ons') && !Schema::hasColumn('add_ons', 'max_qty')) {
            Schema::table('add_ons', function (Blueprint $table) {
                $table->unsignedInteger('max_qty')->nullable()->after('tax');
            });
        }

        if (Schema::hasTable('modifier_template_items') && !Schema::hasColumn('modifier_template_items', 'max_qty')) {
            Schema::table('modifier_template_items', function (Blueprint $table) {
                $table->unsignedInteger('max_qty')->nullable()->after('is_active');
            });
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('add_ons', function (Blueprint $table) {
            $table->dropColumn('max_qty');
        });

        Schema::table('modifier_template_items', function (Blueprint $table) {
            $table->dropColumn('max_qty');
        });
    }
};
