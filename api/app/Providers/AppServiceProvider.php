<?php

namespace App\Providers;

use App\Services\Drafting\LlmDrafter;
use App\Services\Drafting\ReplyDrafter;
use App\Services\Drafting\TemplateDrafter;
use App\Services\Google\DemoGoogleBusiness;
use App\Services\Google\GoogleBusinessProvider;
use App\Services\Google\RealGoogleBusiness;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleBusinessProvider::class, fn ($app) => config('services.google.driver') === 'real'
            ? $app->make(RealGoogleBusiness::class)
            : $app->make(DemoGoogleBusiness::class));

        $this->app->bind(ReplyDrafter::class, fn ($app) => filled(config('services.llm.api_key'))
            ? $app->make(LlmDrafter::class)
            : $app->make(TemplateDrafter::class));
    }

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

        RateLimiter::for('google-refresh', fn (Request $request) => Limit::perMinute(3)
            ->by('google-refresh:'.$request->user()->id)->response($tooMany));

        // Queue-side spacing of sync jobs per owner; RealGoogleBusiness also caps calls per Google account.
        RateLimiter::for('google-sync', fn (object $job) => Limit::perMinute(20)->by('google-sync:'.$job->location->user_id));
    }
}
