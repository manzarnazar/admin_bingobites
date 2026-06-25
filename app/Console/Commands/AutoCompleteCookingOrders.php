<?php

namespace App\Console\Commands;

use App\Model\Order;
use App\Services\OrderStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteCookingOrders extends Command
{
    protected $signature = 'orders:auto-complete-cooking';

    protected $description = 'Automatically mark cooking orders as done when preparation time ends';

    public function __construct(private OrderStatusService $orderStatusService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $completed = 0;
        $checked = 0;

        Order::where('order_status', 'cooking')
            ->with(['customer', 'delivery_man', 'guest'])
            ->chunkById(100, function ($orders) use (&$completed, &$checked) {
                foreach ($orders as $order) {
                    $checked++;
                    $readyAt = $this->orderStatusService->resolveReadyAt($order);

                    if (!$readyAt) {
                        Log::info('AutoCompleteCookingOrders: no ready-at', [
                            'order_id' => $order->id,
                            'preparation_time' => $order->preparation_time,
                        ]);
                        continue;
                    }

                    if ($readyAt->lte(now())) {
                        $isTakeAway = in_array($order->order_type, ['take_away', 'pos'], true);

                        if ($isTakeAway) {
                            $result = $this->orderStatusService->finalizeTakeawayFromCooking($order);
                            if ($result['success']) {
                                $completed++;
                                Log::info('AutoCompleteCookingOrders: takeaway delivered', ['order_id' => $order->id]);
                            }
                        } elseif ($this->orderStatusService->markOrderDone($order, notifyKitchen: true)) {
                            $completed++;
                            Log::info('AutoCompleteCookingOrders: completed', ['order_id' => $order->id]);
                        }
                    }
                }
            });

        $message = "Auto-completed {$completed} cooking order(s) (checked {$checked}).";
        Log::info('AutoCompleteCookingOrders: ' . $message);
        $this->info($message);

        return self::SUCCESS;
    }
}
