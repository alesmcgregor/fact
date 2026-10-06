<?php

namespace App\Exceptions;

use RuntimeException;

class ContentFetchException extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct($failureCode);
    }
}
