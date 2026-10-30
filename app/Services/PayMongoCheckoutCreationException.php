<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class PayMongoCheckoutCreationException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $definitive, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
