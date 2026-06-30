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
        $paidAddon = 8.0;
        $rewardAddon = 4.0;
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
                'addon_cost' => $rewardAddon,
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
            $rewardAddon,
            (float) $lines[1]['promo_line_discount']
        );

        $this->assertEqualsWithDelta(21.95, $paidLine['display_total'], 0.01);
        $this->assertEqualsWithDelta(4.0, $rewardLine['display_total'], 0.01);
        $this->assertTrue($rewardLine['show_free_label']);
        $this->assertStringContainsString('(PAID ITEM)', $paidLine['email_label']);
        $this->assertStringContainsString('(FREE ITEM)', $rewardLine['email_label']);

        $subtotal = $paidLine['core_amount'] + $rewardLine['core_amount'];
        $addons = $paidLine['addon_cost'] + $rewardLine['addon_cost'];
        $totalPaid = $subtotal + $addons - $storedPromotionDiscount;

        $this->assertEqualsWithDelta(27.90, $subtotal, 0.01);
        $this->assertEqualsWithDelta(12.0, $addons, 0.01);
        $this->assertEqualsWithDelta(25.95, $totalPaid, 0.01);
    }

    public function test_non_promo_line_keeps_legacy_display_total(): void
    {
        $item = PromoMailPricing::finalizeNonPromoLineDisplay([
            'email_label' => '1 x Classic Smash Burger',
            'line_price' => 13.95,
            'addon_cost' => 7.0,
            'gross_price' => 13.95,
            'product_discount' => 0.0,
        ]);

        $this->assertEqualsWithDelta(20.95, $item['display_total'], 0.01);
        $this->assertFalse($item['show_free_label']);
        $this->assertNull($item['promotion_role']);
        $this->assertEqualsWithDelta(13.95, $item['core_amount'], 0.01);
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
        $banner->charge_reward_addons = true;
        $banner->setRelation('groupItems', new Collection());

        return $banner;
    }
}
