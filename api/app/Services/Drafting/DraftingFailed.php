<?php

namespace App\Services\Drafting;

use RuntimeException;
use Throwable;

class DraftingFailed extends RuntimeException
{
    /** @param  string  $errorCode  generic code safe to show in the UI; details stay in the message */
    public function __construct(public readonly string $errorCode, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
