<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('banners', 'headline')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->string('headline', 255)->nullable();
            });

            if (Schema::hasColumn('banners', 'title')) {
                DB::table('banners')
                    ->whereNull('headline')
                    ->update(['headline' => DB::raw('`title`')]);
            }
        }

        $promoColumns = [
            'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            'promotion_type' => fn (Blueprint $table) => $table->string('promotion_type')->default('bogo'),
            'reward_discount_value' => fn (Blueprint $table) => $table->decimal('reward_discount_value', 10, 2)->default(100),
            'discount_cheapest_percent' => fn (Blueprint $table) => $table->decimal('discount_cheapest_percent', 10, 2)->nullable(),
            'discount_expensive_percent' => fn (Blueprint $table) => $table->decimal('discount_expensive_percent', 10, 2)->nullable(),
            'charge_paid_addons' => fn (Blueprint $table) => $table->boolean('charge_paid_addons')->default(true),
            'charge_reward_addons' => fn (Blueprint $table) => $table->boolean('charge_reward_addons')->default(false),
            'order_type_mode' => fn (Blueprint $table) => $table->string('order_type_mode')->default('any'),
            'order_types' => fn (Blueprint $table) => $table->json('order_types')->nullable(),
            'payment_methods' => fn (Blueprint $table) => $table->json('payment_methods')->nullable(),
            'once_per_customer' => fn (Blueprint $table) => $table->boolean('once_per_customer')->default(false),
            'max_reward_qty' => fn (Blueprint $table) => $table->unsignedInteger('max_reward_qty')->default(1),
            'usage_per_customer' => fn (Blueprint $table) => $table->unsignedInteger('usage_per_customer')->nullable(),
            'total_usage_limit' => fn (Blueprint $table) => $table->unsignedInteger('total_usage_limit')->nullable(),
            'usage_count' => fn (Blueprint $table) => $table->unsignedInteger('usage_count')->default(0),
            'start_date' => fn (Blueprint $table) => $table->dateTime('start_date')->nullable(),
            'end_date' => fn (Blueprint $table) => $table->dateTime('end_date')->nullable(),
        ];

        foreach ($promoColumns as $column => $definition) {
            if (!Schema::hasColumn('banners', $column)) {
                Schema::table('banners', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }

        foreach (['product_id', 'category_id', 'promotion_id', 'cta_label', 'link_type'] as $legacyColumn) {
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
                'headline',
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

        if (!Schema::hasColumn('banners', 'product_id')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->bigInteger('product_id')->nullable();
            });
        }

        if (!Schema::hasColumn('banners', 'category_id')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->bigInteger('category_id')->nullable();
            });
        }
    }
};
