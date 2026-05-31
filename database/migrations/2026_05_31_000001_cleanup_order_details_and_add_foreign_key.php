<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $validOrderIds = DB::table('orders')->pluck('id');

        DB::table('order_details')
            ->whereNull('order_id')
            ->orWhereNotIn('order_id', $validOrderIds)
            ->delete();

        $staleDetailIds = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->whereColumn('od.created_at', '<', 'o.created_at')
            ->pluck('od.id');

        if ($staleDetailIds->isNotEmpty()) {
            DB::table('order_details')->whereIn('id', $staleDetailIds)->delete();
        }

        $foreignKeyExists = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'order_details'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             AND CONSTRAINT_NAME = 'order_details_order_id_foreign'"
        );

        Schema::table('order_details', function (Blueprint $table) use ($foreignKeyExists) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();

            if (!$foreignKeyExists) {
                $table->foreign('order_id')
                    ->references('id')
                    ->on('orders')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        $foreignKeyExists = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'order_details'
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             AND CONSTRAINT_NAME = 'order_details_order_id_foreign'"
        );

        Schema::table('order_details', function (Blueprint $table) use ($foreignKeyExists) {
            if ($foreignKeyExists) {
                $table->dropForeign(['order_id']);
            }

            $table->bigInteger('order_id')->nullable()->change();
        });
    }
};
