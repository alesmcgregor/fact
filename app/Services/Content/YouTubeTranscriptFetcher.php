<?php

namespace App\Services\Content;

use App\Contracts\HostResolver;
use App\Contracts\PinnedHttpTransport;
use App\Data\FetchedContent;
use App\Data\TransportResponse;
use App\Exceptions\ContentFetchException;
use DOMDocument;
use DOMXPath;

class YouTubeTranscriptFetcher
{
    private const HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    public function __construct(
        private readonly PublicUrlPolicy $policy,
        private readonly HostResolver $resolver,
        private readonly PinnedHttpTransport $transport,
    ) {}

    public function supports(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, self::HOSTS, true);
    }

    public function fetch(string $url): FetchedContent
    {
        try {
            $this->policy->parse($url);
        } catch (\Throwable) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $videoId = $this->videoId($url);

        if ($videoId === null) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $watchUrl = 'https://www.youtube.com/watch?v='.$videoId.'&hl='.rawurlencode(app()->getLocale());
        $watchResponse = $this->get($watchUrl, (int) config('fact.youtube.max_page_bytes'));

        if ($watchResponse->statusCode < 200 || $watchResponse->statusCode >= 300) {
            throw new ContentFetchException('public_content_inaccessible');
        }

        $playerResponse = $this->playerResponse($watchResponse->body);
        if (($playerResponse['playabilityStatus']['status'] ?? 'OK') !== 'OK') {
            throw new ContentFetchException('youtube_access_blocked');
        }
        $trackList = $playerResponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];

        if (! is_array($trackList) || $trackList === []) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $track = $this->selectTrack($trackList, app()->getLocale());
        $captionUrl = is_array($track) ? ($track['baseUrl'] ?? null) : null;

        if (! is_string($captionUrl) || ! $this->isAllowedCaptionUrl($captionUrl)) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $transcriptResponse = $this->get($captionUrl, (int) config('fact.fetch.max_bytes'));

        if ($transcriptResponse->statusCode < 200 || $transcriptResponse->statusCode >= 300) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $text = $this->transcriptText($transcriptResponse->body);

        if ($text === '') {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        return new FetchedContent(
            'YouTube transcript: '.mb_substr((string) ($playerResponse['videoDetails']['title'] ?? 'video'), 0, 450),
            'Transcript: '.mb_substr($text, 0, (int) config('fact.max_input_chars'))."\n\nDescription: ".mb_substr((string) ($playerResponse['videoDetails']['shortDescription'] ?? ''), 0, 2500),
            is_string($track['languageCode'] ?? null) ? mb_substr($track['languageCode'], 0, 12) : null,
            $url,
            ['platform' => 'youtube', 'transcript' => ($track['kind'] ?? null) === 'asr' ? 'automatic' : 'manual',
                'author' => (string) ($playerResponse['videoDetails']['author'] ?? ''),
                'published_at' => $playerResponse['microformat']['playerMicroformatRenderer']['publishDate'] ?? null,
                'truncated' => mb_strlen($text) > (int) config('fact.max_input_chars'), 'full_characters' => mb_strlen($text)],
        );
    }

    private function videoId(string $url): ?string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);

        if ($host === 'youtu.be') {
            $videoId = explode('/', $path)[0] ?? '';
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if (($path === 'watch' || $path === '') && is_string($query['v'] ?? null)) {
                $videoId = $query['v'];
            } elseif (preg_match('~^(?:shorts|embed|live)/([^/]+)$~', $path, $matches) === 1) {
                $videoId = $matches[1];
            } else {
                return null;
            }
        } else {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1 ? $videoId : null;
    }

    /** @return array<string, mixed> */
    private function playerResponse(string $html): array
    {
        if (preg_match('/ytInitialPlayerResponse\s*=\s*/', $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $start = strpos($html, '{', $match[0][1] + strlen($match[0][0]));

        if ($start === false) {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);

        for ($index = $start; $index < $length; $index++) {
            $character = $html[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth === 0) {
                $decoded = json_decode(substr($html, $start, $index - $start + 1), true);

                if (is_array($decoded)) {
                    return $decoded;
                }

                break;
            }
        }

        throw new ContentFetchException('youtube_transcript_unavailable');
    }

    /** @param array<int, mixed> $tracks
     * @return array<string, mixed>|null
     */
    private function selectTrack(array $tracks, string $locale): ?array
    {
        $tracks = array_values(array_filter($tracks, 'is_array'));
        $language = strtolower(substr($locale, 0, 2));

        usort($tracks, function (array $left, array $right) use ($language): int {
            $score = fn (array $track): int => (strtolower((string) ($track['languageCode'] ?? '')) === $language ? 0 : (str_starts_with(strtolower((string) ($track['languageCode'] ?? '')), $language.'-') ? 1 : 2)) * 2
                + (($track['kind'] ?? null) === 'asr' ? 1 : 0);

            return $score($left) <=> $score($right);
        });

        return $tracks[0] ?? null;
    }

    private function isAllowedCaptionUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && in_array($host, ['youtube.com', 'www.youtube.com'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['fragment'])
            && (! isset($parts['port']) || (int) $parts['port'] === 443)
            && ($parts['path'] ?? '') === '/api/timedtext';
    }

    private function get(string $url, int $maxBytes): TransportResponse
    {
        try {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $parsed = $this->policy->parse('https://'.$host.'/');
            $addresses = $this->policy->resolvePublicAddresses($parsed);
        } catch (\Throwable) {
            throw new ContentFetchException('fetch_unavailable');
        }

        $failure = null;

        foreach ($addresses as $address) {
            try {
                return $this->transport->get($url, $address, $maxBytes);
            } catch (ContentFetchException $exception) {
                $failure = $exception;

                if ($exception->failureCode === 'response_too_large') {
                    throw $exception;
                }
            }
        }

        throw $failure ?? new ContentFetchException('fetch_unavailable');
    }

    private function transcriptText(string $body): string
    {
        $json = json_decode($body, true);

        if (is_array($json) && is_array($json['events'] ?? null)) {
            $segments = [];

            foreach ($json['events'] as $event) {
                foreach (($event['segs'] ?? []) as $segment) {
                    if (is_string($segment['utf8'] ?? null)) {
                        $segments[] = $segment['utf8'];
                    }
                }
            }

            return $this->cleanText(implode('', $segments));
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadXML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//text');

        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query('//p');
        }

        $segments = [];

        foreach ($nodes ?: [] as $node) {
            $segment = $this->cleanText($node->textContent);

            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        return $this->cleanText(implode(' ', $segments));
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/[\p{Z}\s]+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8')) ?? '');
    }
}
