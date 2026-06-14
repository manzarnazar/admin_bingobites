<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    /**
     * Run all scheduled tasks (including orders:auto-complete-cooking every minute).
     * Hostinger URL cron example (every minute):
     * curl -s "https://your-domain.com/cron/schedule?token=YOUR_CRON_SECRET"
     */
    public function runScheduler(): Response
    {
        if (!$this->isAuthorized()) {
            abort(403, 'Forbidden');
        }

        Artisan::call('schedule:run');
        $output = trim(Artisan::output());

        $body = $output !== '' ? $output : 'Scheduler executed at ' . now()->toDateTimeString();

        return response($body, 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Run only the cooking auto-complete command (alternative to schedule:run).
     */
    public function autoCompleteCookingOrders(): Response
    {
        if (!$this->isAuthorized()) {
            abort(403, 'Forbidden');
        }

        $exitCode = Artisan::call('orders:auto-complete-cooking');
        $output = trim(Artisan::output());

        return response(
            $output !== '' ? $output : 'Done',
            $exitCode === 0 ? 200 : 500
        )->header('Content-Type', 'text/plain');
    }

    private function isAuthorized(): bool
    {
        $secret = env('CRON_SECRET');

        if (empty($secret)) {
            return false;
        }

        return hash_equals($secret, (string) request('token', ''));
    }
}
