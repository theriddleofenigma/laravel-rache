<?php

declare(strict_types=1);

namespace Rache\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route as IlluminateRoute;
use PHPUnit\Framework\Attributes\Test;
use Rache\Exceptions\InvalidTtlException;
use Rache\Exceptions\MissingRouteNameException;
use Rache\Exceptions\NotInitializedException;
use Rache\Exceptions\UnsupportedCacheStoreException;
use Rache\Rache;
use Rache\Tests\TestCase;

/**
 * Covers the Rache orchestrator directly, without going through the middleware.
 */
class RacheTest extends TestCase
{
    /**
     * Get the Rache instance under test.
     */
    protected function rache(): Rache
    {
        return $this->app->make(Rache::class);
    }

    /**
     * Build a request already bound to a named route.
     *
     * @param  string  $name  The route name to bind.
     * @param  string  $uri  The request URI.
     * @param  string  $method  The HTTP method.
     */
    protected function namedRequest(string $name = 'posts.index', string $uri = '/posts', string $method = 'GET'): Request
    {
        $request = Request::create($uri, $method);
        $route = (new IlluminateRoute([$method], '/posts', ['as' => $name, fn () => null]))->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    #[Test]
    public function it_requires_initialization_before_use(): void
    {
        $this->expectException(NotInitializedException::class);
        $this->expectExceptionMessage('Rache instance is not initialized.');

        $this->rache()->getCachedResponse();
    }

    #[Test]
    public function getting_the_cache_key_requires_initialization(): void
    {
        $this->expectException(NotInitializedException::class);

        $this->rache()->getCacheKey();
    }

    #[Test]
    public function it_exposes_the_route_name_cache_key_and_tags(): void
    {
        $rache = $this->rache()->initialize($this->namedRequest(), ['auth']);

        $this->assertTrue($rache->isInitialized());
        $this->assertSame('posts.index', $rache->getRouteName());
        $this->assertStringStartsWith('laravel-rache-', $rache->getCacheKey());
        $this->assertContains('rache', $rache->getCacheTags());
        $this->assertContains('posts.index:auth', $rache->getCacheTags());
    }

    #[Test]
    public function reset_clears_all_per_request_state(): void
    {
        $rache = $this->rache()->initialize($this->namedRequest(), ['auth']);
        $this->assertTrue($rache->isInitialized());

        $rache->reset();

        $this->assertFalse($rache->isInitialized());
    }

    #[Test]
    public function it_reads_the_ttl_from_a_middleware_parameter(): void
    {
        $rache = $this->rache()->initialize($this->namedRequest(), ['ttl_45', 'auth']);

        $this->assertSame(45, $rache->getLifetime());
    }

    #[Test]
    public function it_falls_back_to_the_configured_lifetime(): void
    {
        config()->set('rache.lifetime', 120);

        $this->assertSame(120, $this->rache()->initialize($this->namedRequest(), ['auth'])->getLifetime());
    }

    #[Test]
    public function a_non_numeric_ttl_reports_the_whole_value(): void
    {
        // Regression: the value was extracted with trim($tag, 'ttl_'), which
        // strips every t, l and _ from both ends, so `ttl_late` was reported
        // as the value "a".
        $this->expectException(InvalidTtlException::class);
        $this->expectExceptionMessage('Non numeric value found for Rache TTL: late');

        $this->rache()->initialize($this->namedRequest(), ['ttl_late']);
    }

    #[Test]
    public function a_zero_ttl_is_rejected(): void
    {
        $this->expectException(InvalidTtlException::class);
        $this->expectExceptionMessage('must be greater than zero');

        $this->rache()->initialize($this->namedRequest(), ['ttl_0']);
    }

    #[Test]
    public function it_rejects_a_request_that_is_not_bound_to_a_route(): void
    {
        $this->expectException(MissingRouteNameException::class);
        $this->expectExceptionMessage('not bound to a route');

        $this->rache()->initialize(Request::create('/posts'), []);
    }

    #[Test]
    public function it_rejects_an_unnamed_route(): void
    {
        $request = Request::create('/posts');
        $route = (new IlluminateRoute(['GET'], '/posts', [fn () => null]))->bind($request);
        $request->setRouteResolver(fn () => $route);

        $this->expectException(MissingRouteNameException::class);
        $this->expectExceptionMessage('/posts');

        $this->rache()->initialize($request, []);
    }

    #[Test]
    public function it_reads_a_lumen_style_array_route(): void
    {
        $request = Request::create('/posts');
        $request->setRouteResolver(fn () => [true, ['as' => 'lumen.posts'], []]);

        $this->assertSame('lumen.posts', $this->rache()->initialize($request, [])->getRouteName());
    }

    #[Test]
    public function a_lumen_route_without_a_name_is_rejected(): void
    {
        // Regression: $route[1]['as'] raised an undefined key warning and then
        // pushed null into the cache tag list.
        $request = Request::create('/posts');
        $request->setRouteResolver(fn () => [true, [], []]);

        $this->expectException(MissingRouteNameException::class);

        $this->rache()->initialize($request, []);
    }

    #[Test]
    public function tag_order_does_not_change_the_cache_key(): void
    {
        $first = $this->rache()->initialize($this->namedRequest(), ['auth', 'page'])->getCacheKey();
        $second = $this->rache()->reset()->initialize($this->namedRequest(), ['page', 'auth'])->getCacheKey();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_round_trips_a_response(): void
    {
        $rache = $this->rache()->initialize($this->namedRequest(), ['auth']);

        $this->assertTrue($rache->cacheResponse(new Response('hello', 200)));

        $rache->reset();
        $rache->initialize($this->namedRequest(), ['auth']);

        $cached = $rache->getCachedResponse();

        $this->assertInstanceOf(Response::class, $cached);
        $this->assertSame('hello', $cached->getContent());
        $this->assertTrue($rache->hasCachedResponse());
    }

    #[Test]
    public function it_reports_no_cached_response_when_disabled(): void
    {
        $rache = $this->rache()->initialize($this->namedRequest(), ['auth']);
        $rache->cacheResponse(new Response('hello'));

        config()->set('rache.enabled', false);

        $this->assertFalse($rache->hasCachedResponse());
        $this->assertNull($rache->getCachedResponse());
        $this->assertFalse($rache->cacheResponse(new Response('other')));
    }

    #[Test]
    public function it_reports_cacheable_request_methods(): void
    {
        $rache = $this->rache();

        $this->assertTrue($rache->shouldCacheRequest(Request::create('/posts', 'GET')));
        $this->assertTrue($rache->shouldCacheRequest(Request::create('/posts', 'HEAD')));
        $this->assertFalse($rache->shouldCacheRequest(Request::create('/posts', 'POST')));
        $this->assertFalse($rache->shouldCacheRequest(Request::create('/posts', 'DELETE')));
    }

    #[Test]
    public function it_explains_that_the_configured_store_cannot_be_tagged(): void
    {
        // Regression: the shipped config defaulted RACHE_DRIVER to `file`, a
        // driver that cannot do tagging, so a fresh install failed with a bare
        // "This cache store does not support tagging." from deep in the
        // framework.
        config()->set('cache.stores.scratch', ['driver' => 'file', 'path' => storage_path('framework/cache/rache')]);
        config()->set('rache.cache_store', 'scratch');

        $this->expectException(UnsupportedCacheStoreException::class);
        $this->expectExceptionMessage('[scratch] cache store does not support tagging');

        $this->rache()->initialize($this->namedRequest(), [])->getCachedResponse();
    }

    #[Test]
    public function initializing_twice_for_the_same_request_is_a_no_op(): void
    {
        $request = $this->namedRequest();
        $rache = $this->rache();

        $first = $rache->initialize($request, ['auth'])->getCacheKey();
        $second = $rache->initialize($request, ['auth'])->getCacheKey();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function initializing_with_different_tags_rebuilds_the_state(): void
    {
        $request = $this->namedRequest();
        $rache = $this->rache();

        $withAuth = $rache->initialize($request, ['auth'])->getCacheKey();
        $withPage = $rache->initialize($request, ['page'])->getCacheKey();

        $this->assertNotSame($withAuth, $withPage);
    }
}
