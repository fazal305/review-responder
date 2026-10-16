<?php

namespace App\Services\Google;

use RuntimeException;

class GoogleApiException extends RuntimeException
{
    /** Seconds to wait before trying again, set when our own per-account throttle refused the call. */
    public ?int $retryAfter = null;

    public static function rateLimited(int $seconds): self
    {
        $e = new self('Google call limit reached for this account.');
        $e->retryAfter = max(1, $seconds);

        return $e;
    }
}
