<?php

namespace App\Console\Commands;

use App\Jobs\SendDailySummary;
use App\Mail\DailySummary;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('summaries:send')]
#[Description('Queue the morning summary email for owners whose chosen local hour is now, once a day, when there is something to report')]
class SendSummaries extends Command
{
    public function handle(): int
    {
        $queued = 0;

        // Demo accounts have no real inbox, so they never get mail.
        User::where('notify_daily', true)->where('is_demo', false)->chunkById(200, function ($users) use (&$queued) {
            foreach ($users as $user) {
                $local = now($user->timezone);
                if ($local->hour !== $user->notify_hour || $user->last_summary_sent_on?->toDateString() === $local->toDateString()) {
                    continue;
                }

                $summary = DailySummary::for($user);
                if (! $summary) {
                    continue;
                }

                // Marked before queueing so an overlapping run can't send twice.
                $user->forceFill(['last_summary_sent_on' => $local->toDateString()])->save();
                SendDailySummary::dispatch($user, $summary);
                $queued++;
            }
        });

        $this->info("Queued {$queued} summary email(s).");

        return self::SUCCESS;
    }
}
