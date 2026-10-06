<?php

namespace App\Data;

final readonly class FetchedContent
{
    public function __construct(
        public string $title,
        public string $text,
        public ?string $language,
        public string $finalUrl,
        public array $metadata = [],
    ) {}
}
