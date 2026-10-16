<?php

namespace Tests\Feature;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const NONCE = 'browser-nonce-0123456789abcdefghijklmnop';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'client-secret',
            'services.google.redirect_uri' => 'http://127.0.0.1:8472/api/auth/google/callback',
            'services.frontend_url' => 'http://localhost:5472',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.plain-access-token',
                'refresh_token' => '1//plain-refresh-token',
                'expires_in' => 3599,
            ]),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => '1098765', 'email' => 'Owner@Example.com', 'email_verified' => true, 'name' => 'Sana Owner',
            ]),
        ]);
    }

    /** Runs redirect + callback and returns the one-time code handed to the SPA. */
    private function completeGoogleConsent(): string
    {
        $url = $this->getJson('/api/auth/google/redirect?nonce='.self::NONCE)->assertOk()->json('url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/business.manage', $query['scope']);

        $location = $this->get('/api/auth/google/callback?code=auth-code&state='.$query['state'])
            ->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('http://localhost:5472/auth/callback#code=', $location);
        $this->assertStringNotContainsString('token', $location);

        return substr($location, strpos($location, '#code=') + 6);
    }

    public function test_google_sign_in_exchanges_one_time_code_for_token(): void
    {
        $code = $this->completeGoogleConsent();

        $res = $this->postJson('/api/auth/exchange', ['code' => $code, 'nonce' => self::NONCE])
            ->assertOk()
            ->assertJsonPath('user.email', 'owner@example.com')
            ->assertJsonPath('user.google_connected', true)
            ->assertJsonMissingPath('user.refresh_token');

        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk();

        // The code is single use.
        $this->postJson('/api/auth/exchange', ['code' => $code, 'nonce' => self::NONCE])->assertUnprocessable();

        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && filled($request['code_verifier']));
    }

    public function test_code_cannot_be_redeemed_with_another_browsers_nonce(): void
    {
        $code = $this->completeGoogleConsent();

        $this->postJson('/api/auth/exchange', ['code' => $code, 'nonce' => str_repeat('x', 40)])->assertUnprocessable();
    }

    public function test_callback_with_unknown_state_is_rejected(): void
    {
        $this->get('/api/auth/google/callback?code=auth-code&state=forged')
            ->assertRedirect('http://localhost:5472/auth/callback#error=expired');

        $this->assertSame(0, User::count());
        Http::assertNothingSent();
    }

    public function test_google_tokens_are_encrypted_at_rest(): void
    {
        $this->completeGoogleConsent();

        $raw = DB::table('google_accounts')->first();
        $this->assertNotSame('1//plain-refresh-token', $raw->refresh_token);
        $this->assertStringNotContainsString('plain-refresh-token', $raw->refresh_token);
        $this->assertStringNotContainsString('plain-access-token', $raw->access_token);
        $this->assertSame('1//plain-refresh-token', Crypt::decryptString($raw->refresh_token));
        $this->assertSame('1//plain-refresh-token', GoogleAccount::first()->refresh_token);
    }

    public function test_google_endpoints_are_off_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->getJson('/api/auth/google/redirect?nonce='.self::NONCE)->assertNotFound();
        $this->getJson('/api/config')->assertJsonPath('googleEnabled', false);
    }
}
