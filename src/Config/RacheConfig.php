<?php

declare(strict_types=1);

namespace Rache\Config;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Typed, normalising accessor for the `rache.*` configuration entries.
 *
 * Every read goes through this class so that the rest of the package never has
 * to defend against a missing key, a stringly typed env value or a config file
 * that predates a newly added option.
 */
class RacheConfig
{
    /**
     * The default response lifetime in seconds, used when none is configured.
     */
    public const DEFAULT_LIFETIME = 3600;

    /**
     * The default cache key prefix, used when none is configured.
     */
    public const DEFAULT_PREFIX = 'laravel-rache';

    /**
     * The request methods that are cached when none are configured.
     *
     * @var list<string>
     */
    public const DEFAULT_CACHEABLE_METHODS = ['GET', 'HEAD'];

    /**
     * Create a new config accessor.
     *
     * @param  ConfigRepository  $config  The application config repository.
     */
    public function __construct(protected readonly ConfigRepository $config)
    {
        //
    }

    /**
     * Determine whether response caching is enabled.
     */
    public function enabled(): bool
    {
        return $this->toBool($this->config->get('rache.enabled'), true);
    }

    /**
     * Get the default response lifetime in seconds.
     */
    public function lifetime(): int
    {
        $lifetime = $this->config->get('rache.lifetime');

        if (! is_numeric($lifetime)) {
            return self::DEFAULT_LIFETIME;
        }

        $lifetime = (int) $lifetime;

        return $lifetime > 0 ? $lifetime : self::DEFAULT_LIFETIME;
    }

    /**
     * Get the prefix applied to every generated cache key.
     */
    public function prefix(): string
    {
        $prefix = $this->config->get('rache.prefix');

        if (! is_string($prefix) && ! is_numeric($prefix)) {
            return self::DEFAULT_PREFIX;
        }

        $prefix = trim((string) $prefix);

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    /**
     * Get the name of the cache store to use, or null for the application default.
     */
    public function cacheStore(): ?string
    {
        $store = $this->config->get('rache.cache_store');

        if (! is_string($store)) {
            return null;
        }

        $store = trim($store);

        return $store !== '' ? $store : null;
    }

    /**
     * Get the registered tag name to tag class map.
     *
     * @return array<string, class-string>
     */
    public function tags(): array
    {
        $tags = $this->config->get('rache.tags');

        if (! is_array($tags)) {
            return [];
        }

        $registered = [];

        foreach ($tags as $name => $class) {
            if (! is_string($name) || ! is_string($class)) {
                continue;
            }

            $name = trim($name);

            if ($name === '') {
                continue;
            }

            /** @var class-string $class */
            $class = ltrim(trim($class), '\\');

            $registered[$name] = $class;
        }

        return $registered;
    }

    /**
     * Get the uppercased request methods whose responses may be cached.
     *
     * @return list<string>
     */
    public function cacheableMethods(): array
    {
        $methods = $this->config->get('rache.cacheable_methods');

        if (! is_array($methods)) {
            return self::DEFAULT_CACHEABLE_METHODS;
        }

        $normalised = [];

        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }

            // HTTP method tokens are case sensitive on the wire but developers
            // routinely write them lowercase in config, so normalise here
            // rather than making every comparison defensive.
            $method = strtoupper(trim($method));

            if ($method !== '' && ! in_array($method, $normalised, true)) {
                $normalised[] = $method;
            }
        }

        return $normalised !== [] ? $normalised : self::DEFAULT_CACHEABLE_METHODS;
    }

    /**
     * Determine whether `Set-Cookie` headers are stripped before a response is cached.
     */
    public function stripSetCookieHeader(): bool
    {
        return $this->toBool($this->config->get('rache.strip_set_cookie_header'), true);
    }

    /**
     * Coerce a loosely typed config value into a boolean.
     *
     * Values arriving from `.env` are strings, so "false", "0" and "off" have to
     * be treated as false rather than as the truthy strings PHP considers them.
     *
     * @param  mixed  $value  The raw config value.
     * @param  bool  $default  The value to use when nothing usable is configured.
     */
    protected function toBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $filtered ?? $default;
    }
}
