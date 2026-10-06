<?php

namespace App\Services\Content;

use App\Data\FetchedContent;
use App\Exceptions\ContentFetchException;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

class ContentExtractor
{
    public function extract(string $html, string $contentType, string $finalUrl): FetchedContent
    {
        $mediaType = strtolower(trim(explode(';', $contentType)[0]));

        if (! in_array($mediaType, ['text/html', 'application/xhtml+xml', 'text/plain'], true)) {
            throw new ContentFetchException('unsupported_content_type');
        }

        if ($mediaType === 'text/plain') {
            $text = $this->cleanText(strip_tags($html));
            $title = parse_url($finalUrl, PHP_URL_HOST) ?: __('app.unknown_page');

            if ($text === '') {
                throw new ContentFetchException('content_unavailable');
            }

            return new FetchedContent(
                Str::substr($title, 0, 500),
                Str::substr($text, 0, (int) config('fact.max_input_chars')),
                null,
                $finalUrl,
            );
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new ContentFetchException('content_unavailable');
        }

        $xpath = new DOMXPath($document);
        $article = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $decoded = json_decode($script->textContent, true, 32);
            if (is_array($decoded)) {
                $article = $this->findArticle($decoded);
                if ($article !== []) {
                    break;
                }
            }
        }

        foreach ($xpath->query('//script|//style|//noscript|//svg|//nav|//footer|//form|//iframe') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        $title = (is_string($article['headline'] ?? null) ? $article['headline'] : null)
            ?? $this->firstValue($xpath, '//meta[@property="og:title"]/@content')
            ?? $this->firstValue($xpath, '//title')
            ?? parse_url($finalUrl, PHP_URL_HOST)
            ?? __('app.unknown_page');
        $description = $this->firstValue($xpath, '//meta[@name="description"]/@content')
            ?? $this->firstValue($xpath, '//meta[@property="og:description"]/@content');
        $language = $this->firstValue($xpath, '/*[local-name()="html"]/@lang');
        $body = (is_string($article['articleBody'] ?? null) ? strip_tags($article['articleBody']) : null)
            ?? $this->firstValue($xpath, '//article') ?? $this->firstValue($xpath, '//main') ?? $this->firstValue($xpath, '//body');
        $text = $this->cleanText(trim(($description ? $description."\n\n" : '').($body ?? '')));
        if ($article === [] && preg_match('/(?:access denied|just a moment|verify you are human|attention required|blocked by network security|enable javascript and cookies)/i', $title.' '.mb_substr($text, 0, 300))) {
            throw new ContentFetchException('public_content_inaccessible');
        }
        $platform = app(SocialContentFetcher::class)->platform($finalUrl);
        if ($platform !== null) {
            $type = $this->firstValue($xpath, '//meta[@property="og:type"]/@content') ?? '';
            if (str_contains($type, 'video') || in_array($platform, ['youtube', 'tiktok'], true)) {
                throw new ContentFetchException('youtube_transcript_unavailable');
            }
            if ($description === null || preg_match('/^(?:log in|login|sign in|instagram|facebook|x|twitter)$/i', trim($title))) {
                throw new ContentFetchException('public_content_inaccessible');
            }
            $text = $this->cleanText($description);
        }

        if (mb_strlen($text) < 20) {
            throw new ContentFetchException('content_unavailable');
        }

        return new FetchedContent(
            Str::substr($this->cleanText($title), 0, 500),
            Str::substr($text, 0, (int) config('fact.max_input_chars')),
            $language ? Str::substr($language, 0, 12) : null,
            $finalUrl,
            ['platform' => $platform ?? 'web', 'extraction' => $article !== [] ? 'structured_article' : 'html',
                'truncated' => mb_strlen($text) > (int) config('fact.max_input_chars'), 'full_characters' => mb_strlen($text)],
        );
    }

    private function findArticle(array $data): array
    {
        $types = (array) ($data['@type'] ?? []);
        if (array_intersect($types, ['Article', 'NewsArticle', 'BlogPosting', 'ReportageNewsArticle']) !== []) {
            return $data;
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $article = $this->findArticle($value);
                if ($article !== []) {
                    return $article;
                }
            }
        }

        return [];
    }

    private function firstValue(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        $value = $nodes?->item(0)?->nodeValue;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
