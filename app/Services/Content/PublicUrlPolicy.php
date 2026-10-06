<?php

namespace App\Services\Content;

use App\Contracts\HostResolver;
use App\Data\ValidatedUrl;
use App\Exceptions\InvalidPublicUrlException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class PublicUrlPolicy
{
    public function __construct(private readonly HostResolver $resolver) {}

    public function parse(string $url): ValidatedUrl
    {
        if (mb_strlen($url) > 2048 || trim($url) !== $url) {
            throw new InvalidPublicUrlException('invalid_url');
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new InvalidPublicUrlException('invalid_url');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidPublicUrlException('unsupported_url');
        }

        if (isset($parts['user']) || isset($parts['pass']) || str_contains($host, '%')) {
            throw new InvalidPublicUrlException('unsupported_url');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $expectedPort = $scheme === 'https' ? 443 : 80;

        if ($port !== $expectedPort) {
            throw new InvalidPublicUrlException('unsupported_url');
        }

        if (function_exists('idn_to_ascii') && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $asciiHost = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

            if ($asciiHost === false) {
                throw new InvalidPublicUrlException('invalid_url');
            }

            $host = strtolower($asciiHost);
        }

        if ($this->isBlockedHostname($host)) {
            throw new InvalidPublicUrlException('private_address');
        }

        $ipLiteral = filter_var($host, FILTER_VALIDATE_IP) ? $host : null;

        if ($ipLiteral !== null && ! self::isPublicIp($ipLiteral)) {
            throw new InvalidPublicUrlException('private_address');
        }

        if ($ipLiteral === null && preg_match('/^(?:[0-9.]+|0x[0-9a-f.]+)$/i', $host) === 1) {
            throw new InvalidPublicUrlException('invalid_url');
        }

        $query = (string) ($parts['query'] ?? '');

        if ($this->containsCredentialParameter($query)) {
            throw new InvalidPublicUrlException('unsupported_url');
        }

        $authorityHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $path = $parts['path'] ?? '/';
        $normalizedUrl = $scheme.'://'.$authorityHost.$path.($query !== '' ? '?'.$query : '');

        return new ValidatedUrl($normalizedUrl, $scheme, $host, $port, $ipLiteral);
    }

    /** @return list<string> */
    public function resolvePublicAddresses(ValidatedUrl $url): array
    {
        $addresses = $url->ipLiteral === null ? $this->resolver->resolve($url->host) : [$url->ipLiteral];

        if ($addresses === []) {
            throw new InvalidPublicUrlException('host_unavailable');
        }

        foreach ($addresses as $address) {
            if (! self::isPublicIp($address)) {
                throw new InvalidPublicUrlException('private_address');
            }
        }

        return array_values(array_unique($addresses));
    }

    public function resolveRedirect(string $currentUrl, string $location): string
    {
        if ($location === '' || mb_strlen($location) > 2048) {
            throw new InvalidPublicUrlException('invalid_redirect');
        }

        return (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
    }

    public static function isPublicIp(string $address): bool
    {
        $packedAddress = @inet_pton($address);

        if ($packedAddress === false) {
            return false;
        }

        if (strlen($packedAddress) === 16 && substr($packedAddress, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $address = inet_ntop(substr($packedAddress, 12));
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function isBlockedHostname(string $host): bool
    {
        if ($host === 'localhost' || ! str_contains($host, '.')) {
            return true;
        }

        foreach (['.localhost', '.local', '.internal', '.test', '.invalid', '.example'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function containsCredentialParameter(string $query): bool
    {
        parse_str($query, $parameters);

        foreach (array_keys($parameters) as $key) {
            if (preg_match('/(?:token|secret|auth|password|session|signature|access[_-]?key|api[_-]?key|^sig$)/i', (string) $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
