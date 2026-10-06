<?php

namespace App\Services\Content;

use App\Data\FetchedContent;
use App\Exceptions\ContentFetchException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class SocialContentFetcher
{
    private const HOSTS = [
        'youtube.com' => 'youtube', 'youtu.be' => 'youtube', 'youtube-nocookie.com' => 'youtube',
        'instagram.com' => 'instagram', 'facebook.com' => 'facebook', 'fb.watch' => 'facebook',
        'tiktok.com' => 'tiktok', 'x.com' => 'x', 'twitter.com' => 'x',
    ];

    public function platform(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::HOSTS as $domain => $platform) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $platform;
            }
        }

        return null;
    }

    public function configured(): bool
    {
        return (string) config('fact.social.key') !== '';
    }

    public function fetch(string $url): FetchedContent
    {
        $platform = $this->platform($url);
        if ($platform === null || ! $this->configured()) {
            throw new ContentFetchException('social_provider_not_configured');
        }
        app(PublicUrlPolicy::class)->parse($url);
        $metadata = $this->request('/metadata', ['url' => $url])->json();
        if (! is_array($metadata)) {
            throw new ContentFetchException('public_content_inaccessible');
        }
        $key = 'transcript-job:'.hash('sha256', $url);
        $jobId = Cache::get($key);
        $response = is_string($jobId)
            ? $this->request('/transcript/'.rawurlencode($jobId))
            : $this->request('/transcript', ['url' => $url, 'text' => 'true', 'mode' => 'native'], true);
        if ($response->status() === 202) {
            $jobId = $response->json('jobId');
            if (! is_string($jobId) || preg_match('/^[A-Za-z0-9-]{1,100}$/', $jobId) !== 1) {
                throw new ContentFetchException('transcript_provider_unavailable');
            }
            Cache::put($key, $jobId, now()->addHour());
        }
        for ($attempt = 0; is_string($jobId) && $attempt < 6 && ! in_array($response->json('status'), ['completed', 'failed'], true); $attempt++) {
            Sleep::for(2)->seconds();
            $response = $this->request('/transcript/'.rawurlencode($jobId));
        }
        if (is_string($jobId) && $response->json('status') !== 'completed') {
            if ($response->json('status') === 'failed') {
                Cache::forget($key);
                throw new ContentFetchException('youtube_transcript_unavailable');
            }
            throw new ContentFetchException('transcript_pending');
        }
        Cache::forget($key);
        $data = $response->json();
        $transcript = is_array($data) ? ($data['content'] ?? '') : '';
        if (is_array($transcript)) {
            $transcript = implode(' ', array_map(fn (array $chunk): string => (string) ($chunk['text'] ?? ''), $transcript));
        }
        if (! is_string($transcript)) {
            $transcript = '';
        }
        $transcript = trim(strip_tags($transcript));
        $title = mb_substr(trim(strip_tags((string) ($metadata['title'] ?? ''))), 0, 500);
        $description = mb_substr(trim(strip_tags((string) ($metadata['description'] ?? ''))), 0, 2500);
        $isVideo = $platform === 'youtube' || $platform === 'tiktok' || ! empty($metadata['duration'])
            || preg_match('~/(?:reel|reels|watch|videos)(?:/|$)~', (string) parse_url($url, PHP_URL_PATH)) === 1;
        if ($isVideo && $transcript === '') {
            throw new ContentFetchException('youtube_transcript_unavailable');
        }
        if ($transcript === '' && mb_strlen($description) < 20) {
            throw new ContentFetchException('content_unavailable');
        }
        $limit = (int) config('fact.max_input_chars');
        $body = $transcript !== '' ? "Transcript:\n".mb_substr($transcript, 0, $limit) : "Post:\n".$description;

        return new FetchedContent($title ?: ucfirst($platform).' post', $body."\n\nTitle: ".$title."\nDescription: ".$description,
            is_string($data['lang'] ?? null) ? mb_substr($data['lang'], 0, 12) : null, $url, [
                'platform' => $platform, 'transcript' => $transcript !== '' ? 'native_provider' : 'unavailable',
                'description' => $description, 'author' => mb_substr((string) ($metadata['author']['name'] ?? ''), 0, 200),
                'published_at' => is_string($metadata['publishedAt'] ?? null) ? mb_substr($metadata['publishedAt'], 0, 40) : null,
                'truncated' => mb_strlen($transcript) > $limit, 'full_characters' => mb_strlen($transcript),
            ]);
    }

    private function request(string $path, array $query = [], bool $allowMissing = false): Response
    {
        try {
            $response = Http::withHeaders(['x-api-key' => config('fact.social.key')])->acceptJson()
                ->connectTimeout(5)->timeout(10)->withOptions(['allow_redirects' => false])
                ->get('https://api.supadata.ai/v1'.$path, $query);
        } catch (\Throwable) {
            throw new ContentFetchException('transcript_provider_unavailable');
        }
        if ($allowMissing && in_array($response->status(), [404, 422], true)) {
            return new Response(new \GuzzleHttp\Psr7\Response(200, [], '{"content":""}'));
        }
        if (! $response->successful() || strlen($response->body()) > (int) config('fact.fetch.max_bytes')) {
            throw new ContentFetchException('transcript_provider_unavailable');
        }

        return $response;
    }
}
