<?php

declare(strict_types=1);

namespace Rache\Exceptions;

/**
 * Thrown when the `ttl_` middleware parameter does not carry a usable value.
 */
class InvalidTtlException extends RacheException
{
    /**
     * Create an exception for a non numeric ttl parameter.
     *
     * @param  string  $value  The raw value that followed the `ttl_` prefix.
     */
    public static function notNumeric(string $value): self
    {
        return new self("Non numeric value found for Rache TTL: $value");
    }

    /**
     * Create an exception for a ttl parameter that is zero or negative.
     *
     * @param  int  $seconds  The parsed lifetime in seconds.
     */
    public static function notPositive(int $seconds): self
    {
        return new self("Rache TTL must be greater than zero, got: $seconds");
    }
}
