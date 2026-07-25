<?php

declare(strict_types=1);

namespace Rache\Exceptions;

use RuntimeException;

/**
 * Base exception for every error raised by Rache.
 *
 * Extends RuntimeException (and therefore \Exception) so that application code
 * written against the pre-2.0 API, which caught the plain \Exception thrown by
 * the old implementation, keeps working unchanged.
 */
class RacheException extends RuntimeException
{
    //
}
