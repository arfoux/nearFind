<?php

namespace App\Support\Seal;

use RuntimeException;
use Throwable;

final class SealException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }
}
