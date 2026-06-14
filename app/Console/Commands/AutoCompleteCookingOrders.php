<?php

namespace App\Console\Commands;

use App\Model\Order;
use App\Services\OrderStatusService;
use Illuminate\Console\Command;

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

        Order::where('order_status', 'cooking')
            ->with(['customer', 'delivery_man', 'guest'])
            ->chunkById(100, function ($orders) use (&$completed) {
                foreach ($orders as $order) {
                    $readyAt = $this->orderStatusService->resolveReadyAt($order);

                    if ($readyAt && $readyAt->lte(now())) {
                        if ($this->orderStatusService->markOrderDone($order, notifyKitchen: true)) {
                            $completed++;
                        }
                    }
                }
            });

        $this->info("Auto-completed {$completed} cooking order(s).");

        return self::SUCCESS;
    }
}
