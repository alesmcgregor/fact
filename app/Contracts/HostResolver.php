<?php

namespace App\Contracts;

interface HostResolver
{
    /** @return list<string> */
    public function resolve(string $hostname): array;
}
