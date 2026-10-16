<?php

namespace Tests\Feature;

use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CronTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_endpoint_rejects_missing_or_wrong_secret(): void
    {
        config(['services.cron.secret' => '']);
        $this->postJson('/api/cron/run', [], ['Authorization' => 'Bearer anything'])->assertUnauthorized();

        config(['services.cron.secret' => 'right-secret']);
        $this->postJson('/api/cron/run')->assertUnauthorized();
        $this->postJson('/api/cron/run', [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_cron_endpoint_runs_the_sync(): void
    {
        config(['services.cron.secret' => 'right-secret', 'services.google.driver' => 'demo', 'services.llm.api_key' => null]);
        Carbon::setTestNow('2026-10-16 02:15:00 UTC');
        $location = Location::factory()->create(['monitoring' => true, 'google_location_name' => 'accounts/sample/locations/1']);

        $this->postJson('/api/cron/run', [], ['Authorization' => 'Bearer right-secret'])
            ->assertOk()
            ->assertJsonPath('sync', 'Queued 1 location sync(s).')
            ->assertJsonPath('summaries', 'Queued 0 summary email(s).')
            ->assertJsonPath('demo', 'Deleted 0 demo account(s).');

        $this->assertSame('2026-10-16', $location->fresh()->last_synced_on->toDateString());
    }
}
