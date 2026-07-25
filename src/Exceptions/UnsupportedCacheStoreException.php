<?php

declare(strict_types=1);

namespace Rache\Exceptions;

use BadMethodCallException;
use Throwable;

/**
 * Thrown when the configured cache store cannot store tagged entries.
 *
 * Rache is built on Laravel cache tags, which the `file`, `database` and
 * `dynamodb` drivers do not implement.
 */
class UnsupportedCacheStoreException extends RacheException
{
    /**
     * Create an exception naming the store that rejected the tagged write.
     *
     * @param  string|null  $store  The configured store name, or null for the app default.
     * @param  BadMethodCallException|null  $previous  The underlying framework exception.
     */
    public static function for(?string $store, ?BadMethodCallException $previous = null): self
    {
        $name = $store ?? 'the application default';

        return new self(
            "The [$name] cache store does not support tagging, which Rache requires. ".
            'Set RACHE_DRIVER (or rache.cache_store) to a taggable store such as redis, '.
            'memcached or array. The file, database and dynamodb drivers cannot be used.',
            0,
            $previous instanceof Throwable ? $previous : null
        );
    }
}
