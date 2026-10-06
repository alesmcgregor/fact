<?php

return [
    'locales' => [
        'en' => 'English',
        'tr' => 'Türkçe',
        'az' => 'Azərbaycanca',
        'ru' => 'Русский',
        'es' => 'Español',
        'ar' => 'العربية',
    ],
    'analysis_driver' => env('FACT_ANALYSIS_DRIVER', 'mock'),
    'content_driver' => env('FACT_CONTENT_DRIVER', 'mock'),
    'max_input_chars' => (int) env('FACT_MAX_INPUT_CHARS', 12000),
    'max_claims' => (int) env('FACT_MAX_CLAIMS', 6),
    'max_sources' => (int) env('FACT_MAX_SOURCES', 12),
    'retention_days' => (int) env('FACT_RETENTION_DAYS', 90),
    'engine_version' => '2026-10-global-3',
    'global_official_domains' => ['un.org', 'who.int', 'worldbank.org', 'imf.org', 'oecd.org', 'europa.eu'],
    'reuse_days' => 7,
    'social' => ['key' => env('SUPADATA_API_KEY', '')],
    'fetch' => [
        'connect_timeout' => (int) env('FACT_FETCH_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('FACT_FETCH_TIMEOUT', 18),
        'max_bytes' => (int) env('FACT_FETCH_MAX_BYTES', 2000000),
        'max_redirects' => (int) env('FACT_FETCH_MAX_REDIRECTS', 5),
    ],
    'youtube' => [
        'max_page_bytes' => (int) env('FACT_YOUTUBE_MAX_PAGE_BYTES', 5000000),
    ],
    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-6-luna'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 90),
    ],
    'independent_domains' => [
        'reuters.com', 'apnews.com', 'bbc.com', 'france24.com', 'dw.com',
        'aljazeera.com', 'rferl.org', 'factcheck.org', 'fullfact.org', 'politifact.com',
    ],
];
