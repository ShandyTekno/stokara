<?php

namespace App\Services\Ai;

final class AiReply
{
    /** @param array<string, mixed> $meta */
    public function __construct(
        public readonly string $text,
        public readonly bool $isSimulation,
        public readonly array $meta = [],
    ) {}
}
