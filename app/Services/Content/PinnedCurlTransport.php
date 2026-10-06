<?php

namespace App\Services\Content;

use App\Contracts\PinnedHttpTransport;
use App\Data\TransportResponse;
use App\Exceptions\ContentFetchException;

class PinnedCurlTransport implements PinnedHttpTransport
{
    public function get(string $url, string $pinnedIp, int $maxBytes): TransportResponse
    {
        $handle = curl_init($url);
        $body = '';
        $headers = [];
        $tooLarge = false;
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? (($parts['scheme'] ?? 'https') === 'https' ? 443 : 80));
        $resolvedIp = str_contains($pinnedIp, ':') ? '['.$pinnedIp.']' : $pinnedIp;

        if ($handle === false) {
            throw new ContentFetchException('fetch_unavailable');
        }

        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => (int) config('fact.fetch.connect_timeout'),
            CURLOPT_TIMEOUT => (int) config('fact.fetch.timeout'),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_RESOLVE => [$host.':'.$port.':'.$resolvedIp],
            CURLOPT_USERAGENT => 'FACT-EvidenceReader/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,text/plain;q=0.8',
                'Accept-Encoding: identity',
                'Connection: close',
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $separator = strpos($line, ':');

                if ($separator !== false) {
                    $headers[strtolower(trim(substr($line, 0, $separator)))] = trim(substr($line, $separator + 1));
                }

                return $length;
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        $succeeded = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $errorCode = curl_errno($handle);
        curl_close($handle);

        if ($tooLarge) {
            throw new ContentFetchException('response_too_large');
        }

        if ($succeeded === false || $errorCode !== CURLE_OK) {
            throw new ContentFetchException('fetch_unavailable');
        }

        if (isset($headers['content-length']) && (int) $headers['content-length'] > $maxBytes) {
            throw new ContentFetchException('response_too_large');
        }

        return new TransportResponse($statusCode, $headers, $body);
    }
}
