<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\BusinessGuideline;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(self::payload($request->user()));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $user->businessGuideline()->updateOrCreate([], Arr::only($data, ['tone', 'strictness', 'sign_off', 'extra_instructions', 'banned_phrases']));
            $user->update(Arr::only($data, ['notify_daily', 'notify_hour', 'timezone']));
        });

        return response()->json(self::payload($user->refresh()) + ['user' => $user->toProfile()]);
    }

    private static function payload(User $user): array
    {
        $guideline = $user->businessGuideline ?? new BusinessGuideline;

        return [
            'guidelines' => [
                'tone' => $guideline->tone,
                'strictness' => $guideline->strictness,
                'sign_off' => $guideline->sign_off,
                'extra_instructions' => $guideline->extra_instructions,
                'banned_phrases' => $guideline->banned_phrases,
            ],
            'notifications' => [
                'notify_daily' => $user->notify_daily,
                'notify_hour' => $user->notify_hour,
                'timezone' => $user->timezone,
            ],
        ];
    }
}
