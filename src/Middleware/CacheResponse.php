<?php

declare(strict_types=1);

namespace Rache\Middleware;

use Closure;
use Illuminate\Http\Request;
use Rache\Rache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a cached response when one exists, and caches the fresh one otherwise.
 *
 * Register it as route middleware and pass the rache tags, plus an optional
 * `ttl_<seconds>` parameter, after the middleware name:
 *
 *     ->middleware('rache:ttl_10,auth,page')
 */
class CacheResponse
{
    /**
     * Create a new middleware instance.
     *
     * @param  Rache  $rache  The rache instance for this request.
     */
    public function __construct(protected readonly Rache $rache)
    {
        //
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure(Request): Response  $next  The next handler.
     * @param  string  ...$params  The rache tags and optional `ttl_<seconds>` parameter.
     *
     * @throws \Rache\Exceptions\RacheException
     */
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        if (! $this->rache->racheEnabled() || ! $this->rache->shouldCacheRequest($request)) {
            return $next($request);
        }

        $rache = $this->rache->initialize($request, $params);

        if (($cached = $rache->getCachedResponse()) !== null) {
            return $cached;
        }

        $response = $next($request);

        if ($this->shouldCacheResponse($response) && $this->hasCacheableContentType($response)) {
            $rache->cacheResponse($response);
        }

        return $response;
    }

    /**
     * Determine whether the given response is one worth caching.
     *
     * @param  Response  $response  The response returned by the route.
     */
    public function shouldCacheResponse(Response $response): bool
    {
        // Streamed and file responses hold a callback or an open file handle,
        // neither of which survives serialization into the cache.
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        return $response->isSuccessful() || $response->isRedirection();
    }

    /**
     * Determine whether the response carries a content type that may be cached.
     *
     * @param  Response  $response  The response returned by the route.
     */
    public function hasCacheableContentType(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type');

        if (! is_string($contentType) || $contentType === '') {
            return false;
        }

        // Media types are case insensitive per RFC 9110, and the header carries
        // parameters ("; charset=utf-8") that must not take part in the match.
        $contentType = strtolower(trim(explode(';', $contentType, 2)[0]));

        if (str_starts_with($contentType, 'text/')) {
            return true;
        }

        return str_ends_with($contentType, '/json')
            || str_ends_with($contentType, '+json');
    }
}
