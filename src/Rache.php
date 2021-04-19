<?php

namespace Rache;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class Rache
{
    protected $cacheKey = null;

    protected $state = [
        'initialize' => false,
        'cached_response' => false,
    ];

    protected $lifetime;

    protected $tags = [];

    protected $cacheTags = [];

    protected $tagSets = [];

    /**
     * @var \Illuminate\Http\Request
     */
    private $request;

    /**
     * @var array|mixed
     */
    protected $cachedResponse;

    public function __construct()
    {
        $this->tagSets = config('rache.tags');
    }

    public function checkInitialized()
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
    public function getCacheKey(): string
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
    public function setLifetimeFromTag($tag)
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
    public function setRequest(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Set the lifetime of the response cache.
     *
     * @param int $seconds
     */
    public function setLifetime(int $seconds)
    {
        $this->lifetime = $seconds;
    }

    /**
     * @throws \Exception
     */
    public function addTag($tag, $resolve = true)
    {
        $this->tags[$tag] = $resolve ? $this->resolveTag($tag) : null;
    }

    /**
     * @throws \Exception
     */
    public function resolveTag($tag)
    {
        $this->tagExists($tag);

        return (new $this->tagSets[$tag]($this->request))->getTagDetails();
    }

    /**
     * Serialize the tags and set the cache tags.
     *
     * @return $this
     */
    public function serializeTags(): Rache
    {
        $this->tags = Arr::sortRecursive($this->tags);
        $this->setDefaultCacheTags();
        $routeName = $this->getCurrentRouteName();
        foreach ($this->tags as $tag => $data) {
            $this->tags[$tag] = serialize($data);
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
    public function getCacheTagForData($tag, $data = null, $routeName = null): string
    {
        $prefix = $routeName ? ':' : '';
        $suffix = $data ? ':' : '';
        return "$routeName{$prefix}$tag{$suffix}$data";
    }

    protected function setDefaultCacheTags()
    {
        $this->cacheTags[] = $this->getCurrentRouteName();
    }

    public function getCurrentRouteName()
    {
        return $this->request->route()[1]['as'];
    }

    /**
     * @throws \Exception
     */
    public function hasCachedResponse()
    {
        if ($this->racheEnabled()) {
            return false;
        }

        return !!$this->getCachedResponse();
    }

    /**
     * @return mixed
     */
    public function racheEnabled()
    {
        return config('rache.enabled');
    }

    /**
     * @throws \Exception
     */
    public function getCachedResponse()
    {
        if ($this->racheEnabled()) {
            return false;
        }

        $this->checkInitialized();
        if (!$this->state['cached_response']) {
            $this->state['cached_response'] = true;
            $this->cachedResponse = Cache::tags($this->cacheTags)->get($this->cacheKey);
        }

        return $this->cachedResponse;
    }

    /**
     * Get the cache lifetime.
     *
     * @return \Laravel\Lumen\Application|mixed
     */
    public function getLifetime()
    {
        return $this->lifetime ?: config('rache.lifetime');
    }

    /**
     * Cache the given response.
     *
     * @param \Illuminate\Http\Response $response
     * @return false
     * @throws \Exception
     */
    public function cacheResponse(Response $response)
    {
        if ($this->racheEnabled()) {
            return false;
        }

        $this->checkInitialized();
        Cache::tags($this->cacheTags)->put($this->cacheKey, $response, $this->getLifetime());
        return true;
    }

    /**
     * @throws \Exception
     */
    public function flushTags($tags)
    {
        $tags = is_array($tags) ? $tags : func_get_args();
        $flushTags = [];
        $routeName = $this->getCurrentRouteName();
        foreach ($tags as $tag) {
            $tagSlug = explode(':', $tag);
            $data = in_array('data', $tagSlug);
            $route = in_array('route', $tagSlug);
            if ($data && $route) {
                $tag = $tagSlug[0];
                $serializedData = serialize(Arr::sortRecursive($this->resolveTag($tag)));
                $flushTags[] = $this->getCacheTagForData($tag, $serializedData, $routeName);
                continue;
            }

            if ($data) {
                $tag = $tagSlug[0];
                $serializedData = serialize(Arr::sortRecursive($this->resolveTag($tag)));
                $flushTags[] = $this->getCacheTagForData($tag, $serializedData);
                continue;
            }

            if ($route) {
                $tag = $tagSlug[0];
                $this->tagExists($tag);
                $flushTags[] = $this->getCacheTagForData($tag, null, $routeName);
                continue;
            }

            $flushTags[] = $tag;
        }

        Cache::tags($flushTags)->flush();
    }

    /**
     * @param $tag
     * @throws \Exception
     */
    public function tagExists($tag): void
    {
        if (!array_key_exists($tag, $this->tagSets)) {
            throw new \Exception("Tag name is not declared in the rache config for $tag");
        }
    }
}