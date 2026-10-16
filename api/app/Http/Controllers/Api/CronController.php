<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Lets an external scheduler run the hourly review sync, morning summaries and demo cleanup on hosts without cron or that
 * sleep when idle. Calls the commands directly rather than schedule:run, because hourly scheduled tasks only fire at
 * minute 0 and external triggers arrive late. Disabled unless CRON_SECRET is set.
 */
class CronController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.cron.secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $lock = Cache::lock('cron:reviews-sync', 300);
        if (! $lock->get()) {
            return response()->json(['message' => 'A run is already in progress.'], 409);
        }

        try {
            set_time_limit(150);
            Artisan::call('reviews:sync');
            $sync = trim(Artisan::output());
            Artisan::call('summaries:send');
            $summaries = trim(Artisan::output());
            Artisan::call('demo:prune');
            $demo = trim(Artisan::output());
            Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 120]);
        } finally {
            $lock->release();
        }

        return response()->json(['message' => 'OK', 'sync' => $sync, 'summaries' => $summaries, 'demo' => $demo]);
    }
}
