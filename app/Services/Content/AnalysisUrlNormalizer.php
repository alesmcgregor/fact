<?php

namespace App\Services\Content;

class AnalysisUrlNormalizer
{
    public function normalize(string $url): string
    {
        $url = app(PublicUrlPolicy::class)->parse(trim($url))->url;
        $parts = parse_url($url);
        $host = strtolower($parts['host']);
        parse_str($parts['query'] ?? '', $query);
        $path = $parts['path'] ?? '/';
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            $id = $host === 'youtu.be' ? explode('/', trim($path, '/'))[0] : ($query['v'] ?? null);
            if (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]{11})/?$~', $path, $match)) {
                $id = $match[1];
            }
            if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
                return 'https://www.youtube.com/watch?v='.$id;
            }
        }
        if (in_array($host, ['twitter.com', 'www.twitter.com', 'www.x.com'], true)) {
            $host = 'x.com';
        }
        foreach (array_keys($query) as $key) {
            if (str_starts_with(strtolower($key), 'utm_') || in_array(strtolower($key), ['fbclid', 'gclid'], true)) {
                unset($query[$key]);
            }
        }
        ksort($query);

        return $parts['scheme'].'://'.$host.$path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }
}
