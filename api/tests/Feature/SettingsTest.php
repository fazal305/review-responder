<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Services\Drafting\LlmDrafter;
use App\Services\Drafting\TemplateDrafter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private const VALID = [
        'tone' => 'professional',
        'strictness' => 'strict',
        'sign_off' => '— Maria, Corner Cafe',
        'extra_instructions' => 'Mention that we are closed on Mondays.',
        'banned_phrases' => "valued customer\nsorry for any inconvenience",
        'notify_daily' => false,
        'notify_hour' => 8,
        'timezone' => 'Europe/London',
    ];

    public function test_defaults_are_returned_before_anything_is_saved(): void
    {
        $this->asUser(User::factory()->create(['timezone' => 'Asia/Karachi']))->getJson('/api/settings')
            ->assertOk()
            ->assertExactJson([
                'guidelines' => ['tone' => 'friendly', 'strictness' => 'balanced', 'sign_off' => null, 'extra_instructions' => null, 'banned_phrases' => null],
                'notifications' => ['notify_daily' => true, 'notify_hour' => 7, 'timezone' => 'Asia/Karachi'],
            ]);
    }

    public function test_settings_are_saved_and_returned(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)->putJson('/api/settings', self::VALID)
            ->assertOk()
            ->assertJsonPath('guidelines.sign_off', '— Maria, Corner Cafe')
            ->assertJsonPath('notifications.notify_hour', 8)
            ->assertJsonPath('user.timezone', 'Europe/London')
            ->assertJsonPath('user.notify_daily', false);

        $this->getJson('/api/settings')->assertJsonPath('guidelines.tone', 'professional')->assertJsonPath('guidelines.strictness', 'strict');
        $this->assertSame(1, $user->businessGuideline()->count());

        $this->putJson('/api/settings', ['sign_off' => ''] + self::VALID)->assertOk()->assertJsonPath('guidelines.sign_off', null);
        $this->assertSame(1, $user->businessGuideline()->count());
    }

    public function test_invalid_values_are_rejected_per_field(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)->putJson('/api/settings', [
            'tone' => 'sarcastic',
            'strictness' => 'loose',
            'sign_off' => str_repeat('a', 121),
            'extra_instructions' => str_repeat('a', 1001),
            'banned_phrases' => str_repeat('a', 1001),
            'notify_daily' => 'sometimes',
            'notify_hour' => 24,
            'timezone' => 'Mars/Olympus',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'tone', 'strictness', 'sign_off', 'extra_instructions', 'banned_phrases', 'notify_daily', 'notify_hour', 'timezone',
        ]);

        $this->putJson('/api/settings', ['notify_hour' => -1] + self::VALID)->assertJsonValidationErrors(['notify_hour' => 'between 0 and 23']);
        $this->assertSame(0, $user->businessGuideline()->count());
    }

    public function test_settings_are_scoped_to_the_signed_in_owner(): void
    {
        $other = User::factory()->create();
        $other->businessGuideline()->create(['tone' => 'casual', 'sign_off' => 'Other owner']);
        $user = User::factory()->create();

        $this->asUser($user)->getJson('/api/settings')->assertJsonPath('guidelines.sign_off', null);
        $this->putJson('/api/settings', self::VALID)->assertOk();

        $this->assertSame('Other owner', $other->businessGuideline->fresh()->sign_off);
        $this->assertSame('casual', $other->businessGuideline->fresh()->tone);
        $this->assertSame(7, $other->fresh()->notify_hour);
    }

    public function test_settings_require_authentication(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
        $this->putJson('/api/settings', self::VALID)->assertUnauthorized();
    }

    public function test_saved_guidelines_reach_the_llm_prompt_and_the_template_sign_off(): void
    {
        $location = Location::factory()->create(['title' => 'Corner Cafe']);
        $this->asUser($location->user)->putJson('/api/settings', self::VALID)->assertOk();

        $review = $location->reviews()->create([
            'google_review_name' => 'accounts/1/locations/2/reviews/1',
            'reviewer_display_name' => 'Nadia R.',
            'star_rating' => 4,
            'comment' => 'Lovely.',
            'reviewed_at' => now(),
        ])->fresh();

        $system = app(LlmDrafter::class)->messages($review)[0]['content'];
        $this->assertStringContainsString('Tone: professional.', $system);
        $this->assertStringContainsString('never admit fault', $system);
        $this->assertStringContainsString('sign-off on its own line: — Maria, Corner Cafe', $system);
        $this->assertStringContainsString('Never use these phrases: valued customer; sorry for any inconvenience.', $system);
        $this->assertStringContainsString("Owner's notes: Mention that we are closed on Mondays.", $system);

        $this->assertStringEndsWith("\n\n— Maria, Corner Cafe", app(TemplateDrafter::class)->draft($review)->reply);
    }
}
