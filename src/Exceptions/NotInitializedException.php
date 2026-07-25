<?php

declare(strict_types=1);

namespace Rache\Exceptions;

/**
 * Thrown when the Rache instance is used before `initialize()` has been called.
 */
class NotInitializedException extends RacheException
{
    /**
     * Create the exception with the default message.
     */
    public static function make(): self
    {
        return new self('Rache instance is not initialized.');
    }
}
