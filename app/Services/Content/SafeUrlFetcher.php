<?php

namespace App\Services\Content;

use App\Contracts\ContentFetcher;
use App\Contracts\PinnedHttpTransport;
use App\Data\FetchedContent;
use App\Exceptions\ContentFetchException;
use App\Exceptions\InvalidPublicUrlException;

class SafeUrlFetcher implements ContentFetcher
{
    public function __construct(
        private readonly PublicUrlPolicy $policy,
        private readonly PinnedHttpTransport $transport,
        private readonly ContentExtractor $extractor,
        private readonly ?YouTubeTranscriptFetcher $youtubeTranscripts = null,
    ) {}

    public function fetch(string $url): FetchedContent
    {
        $social = app(SocialContentFetcher::class);
        if ($social->platform($url) !== null && $social->configured()) {
            return $social->fetch($url);
        }
        if ($this->youtubeTranscripts?->supports($url)) {
            return $this->youtubeTranscripts->fetch($url);
        }

        $currentUrl = $this->policy->parse($url)->url;
        $visited = [];
        $maxRedirects = (int) config('fact.fetch.max_redirects');

        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            if (isset($visited[$currentUrl])) {
                throw new ContentFetchException('redirect_loop');
            }

            $visited[$currentUrl] = true;
            $validated = $this->policy->parse($currentUrl);

            try {
                $addresses = $this->policy->resolvePublicAddresses($validated);
            } catch (InvalidPublicUrlException $exception) {
                throw new ContentFetchException($exception->getMessage());
            } catch (\Throwable) {
                throw new ContentFetchException('fetch_unavailable');
            }

            $response = null;
            $lastFailure = null;

            foreach ($addresses as $address) {
                try {
                    $response = $this->transport->get($validated->url, $address, (int) config('fact.fetch.max_bytes'));
                    break;
                } catch (ContentFetchException $exception) {
                    $lastFailure = $exception;

                    if ($exception->failureCode === 'response_too_large') {
                        throw $exception;
                    }
                }
            }

            if ($response === null) {
                throw $lastFailure ?? new ContentFetchException('fetch_unavailable');
            }

            if (in_array($response->statusCode, [301, 302, 303, 307, 308], true)) {
                if ($redirect === $maxRedirects) {
                    throw new ContentFetchException('too_many_redirects');
                }

                try {
                    $currentUrl = $this->policy->resolveRedirect($validated->url, $response->headers['location'] ?? '');
                    $this->policy->parse($currentUrl);
                } catch (\Throwable) {
                    throw new ContentFetchException('blocked_redirect');
                }

                continue;
            }

            if ($response->statusCode < 200 || $response->statusCode >= 300) {
                throw new ContentFetchException('public_content_inaccessible');
            }

            try {
                return $this->extractor->extract(
                    $response->body,
                    $response->headers['content-type'] ?? '',
                    $validated->url,
                );
            } catch (ContentFetchException $exception) {
                throw $exception;
            } catch (\Throwable) {
                throw new ContentFetchException('content_unavailable');
            }
        }

        throw new ContentFetchException('too_many_redirects');
    }
}
