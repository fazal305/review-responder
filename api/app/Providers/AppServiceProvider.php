<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json(
            ['message' => 'Too many requests. Please wait a minute and try again.'],
            429,
            $headers,
        );

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip())->response($tooMany));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(20)
            ->by('ip:'.$request->ip())->response($tooMany));

        RateLimiter::for('demo', fn (Request $request) => [
            Limit::perMinute(3)->by('demo-min:'.$request->ip())->response($tooMany),
            Limit::perDay(30)->by('demo-day:'.$request->ip())->response($tooMany),
        ]);
    }
}
