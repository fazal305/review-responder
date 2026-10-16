<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GoogleOAuth;
use App\Services\ReviewSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function config(): JsonResponse
    {
        return response()->json([
            'googleEnabled' => GoogleOAuth::enabled(),
            'demoEnabled' => (bool) config('services.demo.login_enabled'),
        ]);
    }

    public function demo(ReviewSync $sync): JsonResponse
    {
        abort_unless(config('services.demo.login_enabled'), 404, 'The demo is not available.');

        $user = User::create([
            'name' => 'Demo owner',
            'email' => 'demo-'.Str::uuid().'@demo.invalid',
            'is_demo' => true,
        ]);
        $sync->seedDemo($user);

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $user->toProfile(),
        ], 201);
    }

    /** Swaps the one-time code from the Google callback for an API token. */
    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:64'],
            'nonce' => ['required', 'string', 'min:32', 'max:128'],
        ]);

        $key = 'login_code:'.hash('sha256', $data['code']);
        $entry = Cache::pull($key);
        // add() is atomic, so a code raced by two requests is only honoured once.
        $firstUse = $entry && Cache::add($key.':used', true, 120);

        if (! $firstUse || ! hash_equals($entry['nonce_hash'], hash('sha256', $data['nonce']))) {
            throw ValidationException::withMessages(['code' => 'This sign-in link has expired. Please sign in again.']);
        }

        $user = User::findOrFail($entry['user_id']);

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $user->toProfile(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->toProfile()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function destroy(Request $request, GoogleOAuth $google): JsonResponse
    {
        $user = $request->user();
        $refreshToken = $user->googleAccount?->refresh_token;

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        if ($refreshToken && GoogleOAuth::enabled()) {
            $google->revoke($refreshToken);
        }

        return response()->json(null, 204);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken('spa', ['*'], now()->addMinutes((int) config('sanctum.expiration')))->plainTextToken;
    }
}
