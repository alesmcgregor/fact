<?php

namespace App\Services\AI;

use App\Contracts\AnalysisProvider;
use App\Data\FetchedContent;

class MockAnalysisProvider implements AnalysisProvider
{
    public function analyze(FetchedContent $content, string $locale): array
    {
        return [
            'summary' => __('app.demo_summary', locale: $locale),
            'claims' => [],
            'signals' => [],
            'limitations' => [__('app.demo_limitation', locale: $locale)],
            'search_performed' => false,
            'demo_mode' => true,
        ];
    }
}
