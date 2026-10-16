<?php

namespace App\Services;

use App\Models\ApiUsageLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public const SCOPES = 'openid email profile https://www.googleapis.com/auth/business.manage';

    public static function enabled(): bool
    {
        $c = config('services.google');

        return filled($c['client_id']) && filled($c['client_secret']) && filled($c['redirect_uri']);
    }

    public function authorizationUrl(string $state, string $codeVerifier): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_in: int} */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $res = $this->call('oauth.token', fn () => Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect_uri'),
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
        ]));

        if (! is_string($res['access_token'] ?? null)) {
            throw new RuntimeException('Google token response had no access token.');
        }

        return [
            'access_token' => $res['access_token'],
            'refresh_token' => $res['refresh_token'] ?? null,
            'expires_in' => (int) ($res['expires_in'] ?? 3600),
        ];
    }

    /** @return array{sub: string, email: string, name: string} */
    public function fetchUser(string $accessToken): array
    {
        $res = $this->call('oauth.userinfo', fn () => Http::withToken($accessToken)->timeout(15)->get(self::USERINFO_URL));

        if (! is_string($res['sub'] ?? null) || ! is_string($res['email'] ?? null) || ($res['email_verified'] ?? false) !== true) {
            throw new RuntimeException('Google account has no verified email.');
        }

        return [
            'sub' => $res['sub'],
            'email' => strtolower($res['email']),
            'name' => is_string($res['name'] ?? null) && $res['name'] !== '' ? $res['name'] : $res['email'],
        ];
    }

    /** Best effort: a failed revoke must not block account deletion. */
    public function revoke(string $token): void
    {
        try {
            $this->call('oauth.revoke', fn () => Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $token]));
        } catch (RuntimeException|ConnectionException) {
            // The grant can still be removed by the user from their Google account settings.
        }
    }

    public static function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** @param  callable(): Response  $request */
    private function call(string $action, callable $request): array
    {
        $started = hrtime(true);
        $ok = false;
        try {
            $response = $request();
            $ok = $response->successful();
            if (! $ok) {
                throw new RuntimeException("Google {$action} failed with HTTP {$response->status()}.");
            }

            return $response->json() ?? [];
        } finally {
            ApiUsageLog::create([
                'service' => 'google',
                'action' => $action,
                'ok' => $ok,
                'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            ]);
        }
    }
}
