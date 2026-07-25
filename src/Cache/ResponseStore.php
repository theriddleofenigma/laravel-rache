<?php

declare(strict_types=1);

namespace Rache\Cache;

use BadMethodCallException;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Rache\Config\RacheConfig;
use Rache\Exceptions\UnsupportedCacheStoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only place in the package that touches the Laravel cache.
 *
 * Keeping the store interaction behind this class means the rest of Rache deals
 * in plain arrays and strings, and the "store does not support tagging" failure
 * is reported once, with an actionable message, instead of leaking out of the
 * framework as a bare BadMethodCallException.
 */
class ResponseStore
{
    /**
     * Create a new response store.
     *
     * @param  CacheFactory  $cache  The cache manager.
     * @param  RacheConfig  $config  The rache config accessor.
     */
    public function __construct(
        protected readonly CacheFactory $cache,
        protected readonly RacheConfig $config,
    ) {
        //
    }

    /**
     * Retrieve the response cached under the given key, if any.
     *
     * @param  list<string>  $tags  The cache tags the response was stored against.
     * @param  string  $key  The cache key to read.
     *
     * @throws UnsupportedCacheStoreException
     */
    public function get(array $tags, string $key): ?Response
    {
        $cached = $this->tagged($tags)->get($key);

        return $cached instanceof Response ? $cached : null;
    }

    /**
     * Store the given response under the given key.
     *
     * @param  list<string>  $tags  The cache tags to store the response against.
     * @param  string  $key  The cache key to write.
     * @param  Response  $response  The response to cache.
     * @param  int  $seconds  The lifetime of the entry in seconds.
     *
     * @throws UnsupportedCacheStoreException
     */
    public function put(array $tags, string $key, Response $response, int $seconds): bool
    {
        return (bool) $this->tagged($tags)->put($key, $response, $seconds);
    }

    /**
     * Flush every entry stored against the given cache tag(s).
     *
     * @param  string|list<string>  $tags  The cache tag(s) to flush.
     *
     * @throws UnsupportedCacheStoreException
     */
    public function flush(string|array $tags): bool
    {
        return (bool) $this->tagged(is_array($tags) ? $tags : [$tags])->flush();
    }

    /**
     * Get a tagged repository for the configured store.
     *
     * @param  list<string>  $tags  The cache tags to scope the repository to.
     *
     * @throws UnsupportedCacheStoreException
     */
    protected function tagged(array $tags): TaggedCache
    {
        $store = $this->config->cacheStore();

        try {
            /** @var \Illuminate\Cache\Repository $repository */
            $repository = $this->repository();

            return $repository->tags($tags);
        } catch (BadMethodCallException $e) {
            throw UnsupportedCacheStoreException::for($store, $e);
        }
    }

    /**
     * Resolve the underlying cache repository for the configured store.
     */
    protected function repository(): Repository
    {
        return $this->cache->store($this->config->cacheStore());
    }
}
