<?php

namespace App\Services\Content;

use App\Contracts\HostResolver;
use RuntimeException;

class DnsHostResolver implements HostResolver
{
    public function resolve(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A | DNS_AAAA);

        if ($records === false) {
            throw new RuntimeException('DNS resolution failed.');
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }
}
