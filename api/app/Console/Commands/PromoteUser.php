<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:promote {email : Email address of an existing account}')]
#[Description('Give an existing (non-demo) account the admin role')]
class PromoteUser extends Command
{
    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error('No account uses that email address.');

            return self::FAILURE;
        }
        if ($user->is_demo) {
            $this->error('Demo accounts cannot be administrators.');

            return self::FAILURE;
        }

        $user->forceFill(['role' => 'admin'])->save();
        $this->info("{$user->email} is now an administrator.");

        return self::SUCCESS;
    }
}
