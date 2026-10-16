<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('demo:prune')]
#[Description('Delete demo accounts, with their sample data and tokens, 24 hours after they were created')]
class PruneDemoAccounts extends Command
{
    public const HOURS = 24;

    public function handle(): int
    {
        $deleted = 0;

        User::where('is_demo', true)->where('created_at', '<', now()->subHours(self::HOURS))
            ->chunkById(200, function ($users) use (&$deleted) {
                foreach ($users as $user) {
                    // Locations, reviews, drafts and guidelines cascade; tokens are polymorphic so they go explicitly.
                    DB::transaction(function () use ($user) {
                        $user->tokens()->delete();
                        $user->delete();
                    });
                    $deleted++;
                }
            });

        $this->info("Deleted {$deleted} demo account(s).");

        return self::SUCCESS;
    }
}
