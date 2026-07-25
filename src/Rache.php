<?php

declare(strict_types=1);

namespace Rache;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Rache\Cache\CacheKeyGenerator;
use Rache\Cache\CacheTagSet;
use Rache\Cache\ResponseStore;
use Rache\Config\RacheConfig;
use Rache\Exceptions\InvalidTtlException;
use Rache\Exceptions\NotInitializedException;
use Rache\Routing\RouteNameResolver;
use Rache\Tags\RacheTagInterface;
use Rache\Tags\TagRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public entry point of the package, bound in the container as `rache`.
 *
 * This class owns the per-request state (which tags apply, what the cache key
 * is, what lifetime was requested) and delegates the actual work to the focused
 * collaborators it is constructed with.
 */
class Rache
{
    /**
     * The middleware parameter prefix that carries a custom lifetime.
     */
    public const TTL_PREFIX = 'ttl_';

    /**
     * Whether `initialize()` has completed for the current request.
     */
    protected bool $initialized = false;

    /**
     * The request the current state was built for.
     */
    protected ?Request $request = null;

    /**
     * The normalised middleware parameters the current state was built for.
     *
     * @var list<string>
     */
    protected array $parameters = [];

    /**
     * The resolved tag data, keyed by tag name.
     *
     * @var array<string, array<array-key, mixed>>
     */
    protected array $tags = [];

    /**
     * The serialized tag data, keyed by tag name.
     *
     * @var array<string, string>
     */
    protected array $serializedTags = [];

    /**
     * The cache tags the current response is stored against.
     *
     * @var list<string>
     */
    protected array $cacheTags = [];

    /**
     * The cache key of the current request.
     */
    protected ?string $cacheKey = null;

    /**
     * The name of the route the current request resolved to.
     */
    protected ?string $routeName = null;

    /**
     * The lifetime override supplied through a `ttl_` middleware parameter.
     */
    protected ?int $lifetime = null;

    /**
     * Whether the cache has already been queried for the current request.
     */
    protected bool $responseFetched = false;

    /**
     * The response found in the cache for the current request, if any.
     */
    protected ?Response $cachedResponse = null;

    /**
     * Create a new Rache instance.
     *
     * @param  RacheConfig  $config  The rache config accessor.
     * @param  TagRegistry  $registry  The configured tag registry.
     * @param  CacheKeyGenerator  $keyGenerator  The cache key generator.
     * @param  CacheTagSet  $tagSet  The cache tag set builder.
     * @param  ResponseStore  $store  The tagged cache store wrapper.
     * @param  RouteNameResolver  $routeNames  The route name resolver.
     */
    public function __construct(
        protected readonly RacheConfig $config,
        protected readonly TagRegistry $registry,
        protected readonly CacheKeyGenerator $keyGenerator,
        protected readonly CacheTagSet $tagSet,
        protected readonly ResponseStore $store,
        protected readonly RouteNameResolver $routeNames,
    ) {
        //
    }

    /**
     * Prepare the instance for the given request and middleware parameters.
     *
     * @param  Request  $request  The request being handled.
     * @param  list<string>  $tags  The raw middleware parameters, e.g. `['ttl_10', 'auth']`.
     * @return $this
     *
     * @throws Exceptions\RacheException
     */
    public function initialize(Request $request, array $tags = []): static
    {
        $parameters = $this->normalizeParameters($tags);

        // A long lived worker (Octane, Swoole, RoadRunner) keeps this singleton
        // between requests. Re-initialising whenever the request or the tag list
        // changes stops a second request from inheriting the first one's cache
        // key, which would serve one visitor's response to another.
        if ($this->initialized && $this->request === $request && $this->parameters === $parameters) {
            return $this;
        }

        $this->reset();

        $this->request = $request;
        $this->parameters = $parameters;
        $this->registry->setRequest($request);

        foreach ($parameters as $parameter) {
            if (str_starts_with($parameter, self::TTL_PREFIX)) {
                $this->setLifetimeFromParameter($parameter);

                continue;
            }

            $this->tags[$parameter] = $this->registry->data($parameter);
        }

        /** @var array<string, array<array-key, mixed>> $sorted */
        $sorted = Arr::sortRecursive($this->tags);
        $this->tags = $sorted;

        foreach ($this->tags as $tag => $data) {
            $this->serializedTags[$tag] = serialize($data);
        }

        $this->routeName = $this->routeNames->resolve($request);
        $this->cacheTags = $this->tagSet->build($this->serializedTags, $this->routeName);
        $this->cacheKey = $this->keyGenerator->generate($request, $this->serializedTags);
        $this->initialized = true;

        return $this;
    }

    /**
     * Discard all per-request state.
     *
     * @return $this
     */
    public function reset(): static
    {
        $this->initialized = false;
        $this->request = null;
        $this->parameters = [];
        $this->tags = [];
        $this->serializedTags = [];
        $this->cacheTags = [];
        $this->cacheKey = null;
        $this->routeName = null;
        $this->lifetime = null;
        $this->responseFetched = false;
        $this->cachedResponse = null;
        $this->registry->flushResolved();

        return $this;
    }

    /**
     * Determine whether the instance has been initialized for a request.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Determine whether response caching is enabled in the config.
     */
    public function racheEnabled(): bool
    {
        return $this->config->enabled();
    }

    /**
     * Determine whether responses to the given request may be cached at all.
     *
     * @param  Request  $request  The request to check.
     */
    public function shouldCacheRequest(Request $request): bool
    {
        return in_array(
            strtoupper($request->getMethod()),
            $this->config->cacheableMethods(),
            true
        );
    }

    /**
     * Determine whether a cached response exists for the current request.
     *
     * @throws Exceptions\RacheException
     */
    public function hasCachedResponse(): bool
    {
        if (! $this->racheEnabled()) {
            return false;
        }

        return $this->getCachedResponse() !== null;
    }

    /**
     * Get the cached response for the current request, if there is one.
     *
     * @throws Exceptions\RacheException
     */
    public function getCachedResponse(): ?Response
    {
        if (! $this->racheEnabled()) {
            return null;
        }

        $this->ensureInitialized();

        if (! $this->responseFetched) {
            $this->responseFetched = true;
            $this->cachedResponse = $this->store->get($this->cacheTags, (string) $this->cacheKey);
        }

        return $this->cachedResponse;
    }

    /**
     * Cache the given response for the current request.
     *
     * @param  Response  $response  The response to store.
     *
     * @throws Exceptions\RacheException
     */
    public function cacheResponse(Response $response): bool
    {
        if (! $this->racheEnabled()) {
            return false;
        }

        $this->ensureInitialized();

        return $this->store->put(
            $this->cacheTags,
            (string) $this->cacheKey,
            $this->prepareForCache($response),
            $this->getLifetime()
        );
    }

    /**
     * Flush the given tag, optionally narrowed to a route and/or a data set.
     *
     * @param  string  $tag  The rache tag name to flush.
     * @param  array{route?: string, data?: array<array-key, mixed>}  $options  Optional scoping.
     *
     * @throws Exceptions\TagNotRegisteredException
     * @throws Exceptions\UnsupportedCacheStoreException
     */
    public function flushTag(string $tag, array $options = []): bool
    {
        $this->registry->ensureRegistered($tag);

        $route = $options['route'] ?? null;
        $data = $options['data'] ?? null;

        return $this->store->flush(CacheTagSet::for(
            $tag,
            is_array($data) ? serialize(Arr::sortRecursive($data)) : null,
            is_string($route) && trim($route) !== '' ? trim($route) : null,
        ));
    }

    /**
     * Flush every response this package has cached.
     *
     * @throws Exceptions\UnsupportedCacheStoreException
     */
    public function flushAll(): bool
    {
        return $this->store->flush(CacheTagSet::GLOBAL_TAG);
    }

    /**
     * Get the tag data for the given tag name.
     *
     * @param  string  $tag  The rache tag name.
     * @return array<array-key, mixed>
     *
     * @throws Exceptions\RacheException
     */
    public function getTagData(string $tag): array
    {
        return $this->registry->data($tag);
    }

    /**
     * Get the tag instance registered under the given name.
     *
     * @param  string  $tag  The rache tag name.
     *
     * @throws Exceptions\RacheException
     */
    public function getTagInstance(string $tag): RacheTagInterface
    {
        return $this->registry->instance($tag);
    }

    /**
     * Get the cache key of the current request.
     *
     * @throws NotInitializedException
     */
    public function getCacheKey(): string
    {
        $this->ensureInitialized();

        return (string) $this->cacheKey;
    }

    /**
     * Get the cache tags the current response is stored against.
     *
     * @return list<string>
     *
     * @throws NotInitializedException
     */
    public function getCacheTags(): array
    {
        $this->ensureInitialized();

        return $this->cacheTags;
    }

    /**
     * Get the name of the route the current request resolved to.
     *
     * @throws NotInitializedException
     */
    public function getRouteName(): string
    {
        $this->ensureInitialized();

        return (string) $this->routeName;
    }

    /**
     * Get the lifetime, in seconds, that the current response will be cached for.
     */
    public function getLifetime(): int
    {
        return $this->lifetime ?? $this->config->lifetime();
    }

    /**
     * Normalise the raw middleware parameters into a clean, unique list.
     *
     * Route definitions are written by hand, so `rache:auth, page` (with a
     * space) and duplicated parameters are both common. Trimming and
     * de-duplicating here keeps a stray space from being reported as an
     * unregistered tag.
     *
     * @param  array<array-key, mixed>  $parameters  The raw middleware parameters.
     * @return list<string>
     */
    protected function normalizeParameters(array $parameters): array
    {
        $normalized = [];

        foreach ($parameters as $parameter) {
            if (! is_string($parameter) && ! is_numeric($parameter)) {
                continue;
            }

            $parameter = trim((string) $parameter);

            if ($parameter !== '' && ! in_array($parameter, $normalized, true)) {
                $normalized[] = $parameter;
            }
        }

        return $normalized;
    }

    /**
     * Parse and apply the lifetime carried by a `ttl_` middleware parameter.
     *
     * @param  string  $parameter  The raw parameter, e.g. `ttl_600`.
     *
     * @throws InvalidTtlException
     */
    protected function setLifetimeFromParameter(string $parameter): void
    {
        // substr(), not trim(): trim() with a character list strips every
        // occurrence of t, l and _ from both ends, so `ttl_late` used to be
        // reported as the value "a".
        $value = substr($parameter, strlen(self::TTL_PREFIX));

        if (! is_numeric($value)) {
            throw InvalidTtlException::notNumeric($value);
        }

        $seconds = (int) $value;

        if ($seconds <= 0) {
            throw InvalidTtlException::notPositive($seconds);
        }

        $this->lifetime = $seconds;
    }

    /**
     * Make a copy of the response that is safe to share between visitors.
     *
     * @param  Response  $response  The response about to be cached.
     */
    protected function prepareForCache(Response $response): Response
    {
        $cacheable = $this->toStorableResponse($response);

        if ($this->config->stripSetCookieHeader()) {
            // Symfony's Response::__clone() clones the header bag, so removing
            // the header here cannot affect the response still being sent to
            // the visitor that generated it.
            $cacheable->headers->remove('Set-Cookie');
        }

        return $cacheable;
    }

    /**
     * Reduce a response to a form that survives serialization.
     *
     * @param  Response  $response  The response about to be cached.
     */
    protected function toStorableResponse(Response $response): Response
    {
        // Illuminate\Http\RedirectResponse holds references to the request and
        // the session, both of which carry closures, so serializing it throws
        // "Serialization of 'Closure' is not allowed" on every store that
        // serializes -- which is every usable store. Redirects were documented
        // as cacheable but blew up with a 500 on the write. Flattening to a
        // plain response keeps the status, headers and body without the
        // unserializable object graph.
        if ($response instanceof RedirectResponse) {
            $flattened = new HttpResponse(
                $response->getContent() === false ? '' : $response->getContent(),
                $response->getStatusCode(),
                $response->headers->all()
            );

            $flattened->setProtocolVersion($response->getProtocolVersion());

            return $flattened;
        }

        return clone $response;
    }

    /**
     * Assert that `initialize()` has been called.
     *
     * @throws NotInitializedException
     */
    protected function ensureInitialized(): void
    {
        if (! $this->initialized) {
            throw NotInitializedException::make();
        }
    }
}
