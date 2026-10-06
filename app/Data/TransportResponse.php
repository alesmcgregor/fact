<?php

namespace App\Data;

final readonly class TransportResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {}
}
