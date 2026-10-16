<?php

namespace App\Services\Drafting;

final readonly class DraftResult
{
    /** @param  list<string>  $themes */
    public function __construct(
        public string $reply,
        public string $sentiment,
        public array $themes,
    ) {}

    public static function sentimentForStars(int $stars): string
    {
        return match (true) {
            $stars >= 4 => 'positive',
            $stars === 3 => 'neutral',
            default => 'negative',
        };
    }
}
