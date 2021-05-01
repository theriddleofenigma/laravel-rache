<?php

namespace Rache\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Rache\Facades\Rache;

class CacheResponse
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param mixed ...$params
     * @return mixed
     * @throws \Exception
     */
    public function handle(Request $request, Closure $next, ...$params)
    {
        $rache = Rache::initialize($request, $params);

        if ($rache->hasCachedResponse()) {
            return $rache->getCachedResponse();
        }

        $response = $next($request);

        if ($this->shouldCacheResponse($response) && $this->hasCacheableContentType($response)) {
            $rache->cacheResponse($response);
        }

        return $response;
    }

    /**
     * Should cache the response.
     *
     * @param \Illuminate\Http\Response $response
     * @return bool
     */
    public function shouldCacheResponse(Response $response): bool
    {
        if ($response->isSuccessful()) {
            return true;
        }

        if ($response->isRedirection()) {
            return true;
        }

        return false;
    }

    public function hasCacheableContentType(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        if (str_starts_with($contentType, 'text/')) {
            return true;
        }

        if (Str::contains($contentType, ['/json', '+json'])) {
            return true;
        }

        return false;
    }
}
