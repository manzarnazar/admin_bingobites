<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'description')) {
                $table->text('description')->nullable()->after('headline');
            }
            if (!Schema::hasColumn('banners', 'promotion_type')) {
                $table->string('promotion_type')->default('bogo')->after('description');
            }
            if (!Schema::hasColumn('banners', 'reward_discount_value')) {
                $table->decimal('reward_discount_value', 10, 2)->default(100)->after('promotion_type');
            }
            if (!Schema::hasColumn('banners', 'discount_cheapest_percent')) {
                $table->decimal('discount_cheapest_percent', 10, 2)->nullable()->after('reward_discount_value');
            }
            if (!Schema::hasColumn('banners', 'discount_expensive_percent')) {
                $table->decimal('discount_expensive_percent', 10, 2)->nullable()->after('discount_cheapest_percent');
            }
            if (!Schema::hasColumn('banners', 'charge_paid_addons')) {
                $table->boolean('charge_paid_addons')->default(true)->after('discount_expensive_percent');
            }
            if (!Schema::hasColumn('banners', 'charge_reward_addons')) {
                $table->boolean('charge_reward_addons')->default(false)->after('charge_paid_addons');
            }
            if (!Schema::hasColumn('banners', 'order_type_mode')) {
                $table->string('order_type_mode')->default('any')->after('charge_reward_addons');
            }
            if (!Schema::hasColumn('banners', 'order_types')) {
                $table->json('order_types')->nullable()->after('order_type_mode');
            }
            if (!Schema::hasColumn('banners', 'payment_methods')) {
                $table->json('payment_methods')->nullable()->after('order_types');
            }
            if (!Schema::hasColumn('banners', 'once_per_customer')) {
                $table->boolean('once_per_customer')->default(false)->after('payment_methods');
            }
            if (!Schema::hasColumn('banners', 'max_reward_qty')) {
                $table->unsignedInteger('max_reward_qty')->default(1)->after('once_per_customer');
            }
            if (!Schema::hasColumn('banners', 'usage_per_customer')) {
                $table->unsignedInteger('usage_per_customer')->nullable()->after('max_reward_qty');
            }
            if (!Schema::hasColumn('banners', 'total_usage_limit')) {
                $table->unsignedInteger('total_usage_limit')->nullable()->after('usage_per_customer');
            }
            if (!Schema::hasColumn('banners', 'usage_count')) {
                $table->unsignedInteger('usage_count')->default(0)->after('total_usage_limit');
            }
            if (!Schema::hasColumn('banners', 'start_date')) {
                $table->dateTime('start_date')->nullable()->after('usage_count');
            }
            if (!Schema::hasColumn('banners', 'end_date')) {
                $table->dateTime('end_date')->nullable()->after('start_date');
            }
        });

        if (Schema::hasColumn('banners', 'promotion_id')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->dropColumn('promotion_id');
            });
        }

        foreach (['cta_label', 'link_type'] as $legacyColumn) {
            if (Schema::hasColumn('banners', $legacyColumn)) {
                Schema::table('banners', function (Blueprint $table) use ($legacyColumn) {
                    $table->dropColumn($legacyColumn);
                });
            }
        }

        if (!Schema::hasTable('banner_group_items')) {
            Schema::create('banner_group_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
                $table->unsignedTinyInteger('group_number');
                $table->unsignedBigInteger('product_id');
                $table->json('variations')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->index(['banner_id', 'group_number']);
            });
        }

        if (!Schema::hasTable('promotion_usages')) {
            Schema::create('promotion_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('guest_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->timestamps();

                $table->index(['banner_id', 'user_id']);
                $table->index(['banner_id', 'guest_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_usages');
        Schema::dropIfExists('banner_group_items');

        Schema::table('banners', function (Blueprint $table) {
            $columns = [
                'description',
                'promotion_type',
                'reward_discount_value',
                'discount_cheapest_percent',
                'discount_expensive_percent',
                'charge_paid_addons',
                'charge_reward_addons',
                'order_type_mode',
                'order_types',
                'payment_methods',
                'once_per_customer',
                'max_reward_qty',
                'usage_per_customer',
                'total_usage_limit',
                'usage_count',
                'start_date',
                'end_date',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('banners', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
