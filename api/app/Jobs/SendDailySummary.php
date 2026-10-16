<?php

namespace App\Jobs;

use App\Mail\DailySummary;
use App\Models\ApiUsageLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/** Sends one owner's summary from the queue and records the attempt in the usage log. */
class SendDailySummary implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $user, public DailySummary $summary) {}

    public function handle(): void
    {
        $started = hrtime(true);
        $ok = false;
        try {
            Mail::to($this->user)->send($this->summary);
            $ok = true;
        } finally {
            ApiUsageLog::create([
                'user_id' => $this->user->id,
                'service' => 'mail',
                'action' => 'summary.send',
                'ok' => $ok,
                'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
            ]);
        }
    }
}
