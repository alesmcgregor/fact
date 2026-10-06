<?php

namespace App\Contracts;

use App\Data\FetchedContent;

interface ContentFetcher
{
    public function fetch(string $url): FetchedContent;
}
