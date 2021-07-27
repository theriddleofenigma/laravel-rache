<?php

namespace Rache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Rache\Tags\RacheTagInterface;

class Rache
{
    /**
     * @var null
     */
    protected $cacheKey = null;

    /**
     * @var false[]
     */
    protected $state = [
        'initialize' => false,
        'cached_response' => false,
    ];

    /**
     * @var
     */
    protected $lifetime;

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $cacheTags = [];

    /**
     * @var array
     */
    protected $tagSets = [];

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * @var array|mixed
     */
    protected $cachedResponse;

    /**
     * Rache constructor.
     */
    public function __construct()
    {
        $this->tagSets = config('rache.tags');
        $this->setRequest(request());
    }

    /**
     * @throws \Exception
     */
    protected function checkInitialized()
    {
        if (!$this->state['initialize']) {
            throw new \Exception('Rache instance is not initialized.');
        }
    }

    /**
     * @throws \Exception
     */
    public function initialize($request, $tags): Rache
    {
        if ($this->state['initialize']) {
            return $this;
        }

        $this->setRequest($request);

        foreach ($tags as $tag) {
            if (strpos($tag, 'ttl_') === 0) {
                $this->setLifetimeFromTag($tag);
                continue;
            }

            $this->addTag($tag);
        }

        $this->state['initialize'] = true;
        $this->cacheKey = $this->serializeTags()->getCacheKey();

        return $this;
    }

    /**
     * Get the cache key.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        $tagKeys = '';
        foreach ($this->tags as $tag => $data) {
            $tagKeys .= "$tag.$data";
        }

        $key = implode(':', [
            $this->request->getHost(),
            trim($this->request->getPathInfo(), '/'),
            $tagKeys,
        ]);

        return config('rache.prefix') . '-' . hash('sha256', $key);
    }

    /**
     * Set the lifetime of the response cache from the given tag.
     *
     * @param $tag
     * @throws \Exception
     */
    protected function setLifetimeFromTag($tag)
    {
        $value = trim($tag, 'ttl_');
        if (!is_numeric($value)) {
            throw new \Exception("Non numeric value found for Rache TTL: $value");
        }

        $this->setLifetime((int)$value);
    }

    /**
     * Set the request.
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Set the lifetime of the response cache.
     *
     * @param int $seconds
     */
    protected function setLifetime(int $seconds)
    {
        $this->lifetime = $seconds;
    }

    /**
     * @throws \Exception
     */
    protected function addTag($tag)
    {
        $this->tags[$tag] = $this->getTagData($tag);
    }

    /**
     * Get the tag data.
     *
     * @throws \Exception
     */
    public function getTagData($tag): array
    {
        return $this->getTagInstance($tag)->getTagDetails();
    }

    /**
     * Get the tag instance.
     *
     * @param $tag
     * @return \Rache\Tags\RacheTagInterface
     * @throws \Exception
     */
    protected function getTagInstance($tag): RacheTagInterface
    {
        $this->tagExists($tag);

        $tagInstance = new $this->tagSets[$tag]($this->request);
        if (!$tagInstance instanceof RacheTagInterface) {
            throw new \Exception("All the tags should implement the RacheTagInterface. Check the $tag Tag class whether it implements RacheTagInterface.");
        }
        return $tagInstance;
    }

    /**
     * Serialize the tags and set the cache tags.
     *
     * @return $this
     */
    protected function serializeTags(): Rache
    {
        $this->tags = Arr::sortRecursive($this->tags);
        $this->setDefaultCacheTags();
        $routeName = $this->getCurrentRouteName();
        foreach ($this->tags as $tag => $data) {
            $this->tags[$tag] = serialize($data);
            $this->cacheTags[] = 'rache';
            $this->cacheTags[] = $tag;
            $this->cacheTags[] = $this->getCacheTagForData($tag, $this->tags[$tag]);
            $this->cacheTags[] = $this->getCacheTagForData($tag, null, $routeName);
            $this->cacheTags[] = $this->getCacheTagForData($tag, $this->tags[$tag], $routeName);
        }

        return $this;
    }

    /**
     * Get the serialized cache tag.
     *
     * @param $tag
     * @param $data
     * @param null $routeName
     * @return string
     */
    protected function getCacheTagForData($tag, $data = null, $routeName = null): string
    {
        $prefix = $routeName ? ':' : '';
        $suffix = $data ? ':' : '';
        return "$routeName{$prefix}$tag{$suffix}$data";
    }

    /**
     * Set the default cache tags.
     */
    protected function setDefaultCacheTags()
    {
        $this->cacheTags[] = $this->getCurrentRouteName();
    }

    /**
     * Get the current route name.
     *
     * @return mixed
     */
    protected function getCurrentRouteName()
    {
        $route = $this->request->route();
        if (is_array($route)) {
            return $route[1]['as'];
        }

        return $this->request->route()->getAction('as');
    }

    /**
     * Check whether the cached response exists.
     *
     * @return bool
     * @throws \Exception
     */
    public function hasCachedResponse(): bool
    {
        if (!$this->racheEnabled()) {
            return false;
        }

        return !!$this->getCachedResponse();
    }

    /**
     * Check whether the rache is enabled in the config.
     *
     * @return mixed
     */
    public function racheEnabled()
    {
        return config('rache.enabled');
    }

    /**
     * Get the cached response.
     *
     * @return array|false|mixed
     * @throws \Exception
     */
    public function getCachedResponse()
    {
        if (!$this->racheEnabled()) {
            return false;
        }

        $this->checkInitialized();
        if (!$this->state['cached_response']) {
            $this->state['cached_response'] = true;
            $this->cachedResponse = $this->cache()->tags($this->cacheTags)->get($this->cacheKey);
        }

        return $this->cachedResponse;
    }

    /**
     * Get the cache lifetime.
     *
     * @return mixed
     */
    protected function getLifetime()
    {
        return $this->lifetime ?: config('rache.lifetime');
    }

    /**
     * Cache the given response.
     *
     * @param \Illuminate\Http\Response $response
     * @return bool
     * @throws \Exception
     */
    public function cacheResponse(Response $response): bool
    {
        if (!$this->racheEnabled()) {
            return false;
        }

        $this->checkInitialized();
        $this->cache()->tags($this->cacheTags)->put($this->cacheKey, $response, $this->getLifetime());
        return true;
    }

    /**
     * Flush the given tag for the given route and data.
     *
     * @throws \Exception
     */
    public function flushTag($tag, $options = [])
    {
        $this->tagExists($tag);
        $data = isset($options['data']);
        $route = isset($options['route']);
        if ($data && $route) {
            $serializedData = serialize(Arr::sortRecursive($options['data']));
            $tag = $this->getCacheTagForData($tag, $serializedData, $options['route']);
        } elseif ($data) {
            $serializedData = serialize(Arr::sortRecursive($options['data']));
            $tag = $this->getCacheTagForData($tag, $serializedData);
        } elseif ($route) {
            $tag = $this->getCacheTagForData($tag, null, $options['route']);
        }

        $this->cache()->tags($tag)->flush();
    }

    /**
     * Flush all the tag.
     */
    public function flushAll()
    {
        $this->cache()->tags('rache')->flush();
    }

    /**
     * @param $tag
     * @throws \Exception
     */
    protected function tagExists($tag): void
    {
        if (!array_key_exists($tag, $this->tagSets)) {
            throw new \Exception("Tag name is not declared in the rache config for $tag");
        }
    }

    /**
     * Get the cache for the configured store.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    protected function cache(): Repository
    {
        return Cache::store(config('rache.cache_store'));
    }
}