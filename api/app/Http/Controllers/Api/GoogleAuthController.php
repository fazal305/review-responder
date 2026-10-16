<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleOAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Returns the Google consent URL. The SPA sends a random nonce it keeps in
     * sessionStorage; the one-time code is only redeemable with that nonce, which
     * ties the finished sign-in to the browser that started it.
     */
    public function redirect(Request $request, GoogleOAuth $google): JsonResponse
    {
        abort_unless(GoogleOAuth::enabled(), 404, 'Google sign-in is not configured.');

        $data = $request->validate(['nonce' => ['required', 'string', 'min:32', 'max:128']]);

        $state = Str::random(40);
        $verifier = Str::random(64);
        Cache::put('oauth_state:'.$state, [
            'verifier' => $verifier,
            'nonce_hash' => hash('sha256', $data['nonce']),
        ], now()->addMinutes(10));

        return response()->json(['url' => $google->authorizationUrl($state, $verifier)]);
    }

    public function callback(Request $request, GoogleOAuth $google): RedirectResponse
    {
        abort_unless(GoogleOAuth::enabled(), 404, 'Google sign-in is not configured.');

        $state = $request->query('state');
        $pending = is_string($state) ? Cache::pull('oauth_state:'.$state) : null;

        if (! $pending) {
            return $this->toSpa(['error' => 'expired']);
        }
        if ($request->query('error') || ! is_string($request->query('code'))) {
            return $this->toSpa(['error' => 'denied']);
        }

        try {
            $tokens = $google->exchangeCode($request->query('code'), $pending['verifier']);
            $profile = $google->fetchUser($tokens['access_token']);
            $user = $this->upsertUser($profile, $tokens);
        } catch (Throwable $e) {
            Log::warning('Google sign-in failed', ['error' => $e->getMessage()]);

            return $this->toSpa(['error' => 'failed']);
        }

        $code = Str::random(64);
        Cache::put('login_code:'.hash('sha256', $code), [
            'user_id' => $user->id,
            'nonce_hash' => $pending['nonce_hash'],
        ], now()->addMinute());

        return $this->toSpa(['code' => $code]);
    }

    private function upsertUser(array $profile, array $tokens): User
    {
        return DB::transaction(function () use ($profile, $tokens) {
            $user = User::firstOrNew(['google_sub' => $profile['sub']]);
            $user->fill(['name' => $profile['name'], 'email' => $profile['email']])->save();

            $account = $user->googleAccount()->firstOrNew();
            $refreshToken = $tokens['refresh_token'] ?? $account->refresh_token;
            if (! $refreshToken) {
                throw new \RuntimeException('Google did not return a refresh token.');
            }
            $account->fill([
                'refresh_token' => $refreshToken,
                'access_token' => $tokens['access_token'],
                'expires_at' => now()->addSeconds($tokens['expires_in']),
            ])->save();

            return $user;
        });
    }

    // The code travels in the fragment so it never reaches server logs or Referer headers.
    private function toSpa(array $params): RedirectResponse
    {
        return redirect()->away(config('services.frontend_url').'/auth/callback#'.http_build_query($params));
    }
}
