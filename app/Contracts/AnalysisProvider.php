<?php

namespace App\Contracts;

use App\Data\FetchedContent;

interface AnalysisProvider
{
    /** @return array<string, mixed> */
    public function analyze(FetchedContent $content, string $locale): array;
}
