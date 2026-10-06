<?php

namespace App\Contracts;

use App\Data\TransportResponse;

interface PinnedHttpTransport
{
    public function get(string $url, string $pinnedIp, int $maxBytes): TransportResponse;
}
