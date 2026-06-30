<?php

namespace Tests\Unit;

use App\CentralLogics\PromoMailPricing;
use App\Model\Banner;
use App\Services\PromoOrderService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BingoBitesOrderMailHelperTest extends TestCase
{
    public function test_bogo_paid_and_reward_line_display_totals(): void
    {
        $promoService = new PromoOrderService();
        $banner = $this->makeBogoBanner();

        $paidCore = 13.95;
        $rewardCore = 13.95;
        $paidAddon = 11.0;
        $storedPromotionDiscount = 13.95;

        $paidRaw = PromoMailPricing::computeRawPromoLineDiscount(
            $banner,
            $promoService,
            'paid',
            $paidCore,
            ['product_id' => 1, 'variations' => []]
        );
        $rewardRaw = PromoMailPricing::computeRawPromoLineDiscount(
            $banner,
            $promoService,
            'reward',
            $rewardCore,
            ['product_id' => 1, 'variations' => []]
        );

        $this->assertEquals(0.0, $paidRaw);
        $this->assertEqualsWithDelta(13.95, $rewardRaw, 0.01);

        $lines = PromoMailPricing::allocatePromoLineDiscounts([
            [
                'email_label' => '1 x Classic Smash Burger',
                'line_price' => $paidCore,
                'addon_cost' => $paidAddon,
                'core_amount' => $paidCore,
                '_raw_promo_discount' => $paidRaw,
                '_line_net' => $paidCore,
                'promotion_role' => 'paid',
            ],
            [
                'email_label' => '1 x Classic Smash Burger',
                'line_price' => $rewardCore,
                'addon_cost' => 0.0,
                'core_amount' => $rewardCore,
                '_raw_promo_discount' => $rewardRaw,
                '_line_net' => $rewardCore,
                'promotion_role' => 'reward',
            ],
        ], $storedPromotionDiscount);

        $paidLine = PromoMailPricing::finalizePromoLineDisplay(
            $lines[0],
            'paid',
            $paidCore,
            $paidCore,
            $paidAddon,
            (float) $lines[0]['promo_line_discount']
        );
        $rewardLine = PromoMailPricing::finalizePromoLineDisplay(
            $lines[1],
            'reward',
            $rewardCore,
            $rewardCore,
            0.0,
            (float) $lines[1]['promo_line_discount']
        );

        $this->assertEqualsWithDelta(13.95, $paidLine['display_total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $rewardLine['display_total'], 0.01);
        $this->assertTrue($rewardLine['show_free_label']);
        $this->assertStringContainsString('(PAID ITEM)', $paidLine['email_label']);
        $this->assertStringContainsString('(FREE ITEM)', $rewardLine['email_label']);

        $subtotal = $paidLine['core_amount'] + $rewardLine['core_amount'];
        $addons = $paidLine['addon_cost'] + $rewardLine['addon_cost'];
        $totalPaid = $subtotal + $addons - $storedPromotionDiscount;

        $this->assertEqualsWithDelta(27.90, $subtotal, 0.01);
        $this->assertEqualsWithDelta(11.0, $addons, 0.01);
        $this->assertEqualsWithDelta(24.95, $totalPaid, 0.01);
    }

    public function test_non_promo_line_shows_core_only_in_price_column(): void
    {
        $item = PromoMailPricing::finalizeNonPromoLineDisplay([
            'email_label' => '1 x Classic Smash Burger',
            'line_price' => 24.95,
            'addon_cost' => 7.0,
            'core_amount' => 13.95,
            'gross_price' => 13.95,
            'product_discount' => 0.0,
        ]);

        $this->assertEqualsWithDelta(13.95, $item['display_total'], 0.01);
        $this->assertEqualsWithDelta(7.0, $item['addon_cost'], 0.01);
        $this->assertFalse($item['show_free_label']);
        $this->assertNull($item['promotion_role']);
    }

    public function test_paid_line_does_not_include_addons_in_price_column(): void
    {
        $paidLine = PromoMailPricing::finalizePromoLineDisplay(
            ['email_label' => '1 x Classic Smash Burger'],
            'paid',
            32.95,
            13.95,
            11.0,
            0.0
        );

        $this->assertEqualsWithDelta(13.95, $paidLine['display_total'], 0.01);
        $this->assertEqualsWithDelta(11.0, $paidLine['addon_cost'], 0.01);
        $this->assertFalse($paidLine['show_free_label']);
    }

    public function test_reward_line_ignores_stored_addons_when_not_charged(): void
    {
        $rewardLine = PromoMailPricing::finalizePromoLineDisplay(
            ['email_label' => '1 x Classic Smash Burger'],
            'reward',
            13.95,
            13.95,
            0.0,
            13.95
        );

        $this->assertEqualsWithDelta(0.0, $rewardLine['display_total'], 0.01);
        $this->assertTrue($rewardLine['show_free_label']);
    }

    public function test_compute_mail_core_amount_includes_size_variation_only(): void
    {
        $promoService = new PromoOrderService();
        $basePrice = 13.95;
        $productVariations = [
            [
                'name' => 'Size',
                'type' => 'single',
                'values' => [
                    ['label' => 'Regular', 'optionPrice' => 0],
                    ['label' => 'Large', 'optionPrice' => 3],
                ],
            ],
            [
                'name' => 'Burger Addon',
                'type' => 'multi',
                'values' => [
                    ['label' => 'Chicken Patty', 'optionPrice' => 4],
                    ['label' => 'Beef Patty', 'optionPrice' => 4],
                ],
            ],
        ];
        $cartVariations = [
            [
                'name' => 'Size',
                'values' => ['label' => ['Large']],
            ],
            [
                'name' => 'Burger Addon',
                'values' => ['label' => ['Chicken Patty', 'Beef Patty']],
            ],
        ];

        $coreAmount = PromoMailPricing::computeMailCoreAmount(
            $promoService,
            $basePrice,
            $productVariations,
            $cartVariations,
            ['discount_type' => 'amount', 'discount' => 0],
            1
        );

        $this->assertEqualsWithDelta(16.95, $coreAmount, 0.01);
    }

    public function test_build_promotion_label_for_bogo(): void
    {
        $banner = $this->makeBogoBanner();
        $banner->headline = 'bogo';
        $banner->title = 'BOGO Deal';

        $this->assertSame(
            'Discount (bogo – Free item)',
            PromoMailPricing::buildPromotionLabel($banner)
        );
    }

    public function test_stored_variations_convert_to_cart_format(): void
    {
        $cart = PromoMailPricing::storedVariationsToCartFormat([
            [
                'name' => 'Size',
                'values' => [
                    ['label' => 'Regular', 'optionPrice' => 2, 'qty' => 1],
                ],
            ],
        ]);

        $this->assertSame('Size', $cart[0]['name']);
        $this->assertSame(['Regular'], $cart[0]['values']['label']);
    }

    private function makeBogoBanner(): Banner
    {
        $banner = new Banner();
        $banner->promotion_type = Banner::PROMOTION_TYPE_BOGO;
        $banner->reward_discount_value = 100;
        $banner->charge_paid_addons = true;
        $banner->charge_reward_addons = false;
        $banner->setRelation('groupItems', new Collection());

        return $banner;
    }
}
