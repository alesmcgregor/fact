<?php

namespace App\Data;

final readonly class ValidatedUrl
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public ?string $ipLiteral,
    ) {}
}
