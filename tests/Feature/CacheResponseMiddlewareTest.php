<?php

declare(strict_types=1);

namespace Rache\Tests\Feature;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Rache\Exceptions\MissingRouteNameException;
use Rache\Facades\Rache;
use Rache\Tests\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * End to end coverage of the caching middleware.
 */
class CacheResponseMiddlewareTest extends TestCase
{
    /**
     * The number of times the route handler has actually run.
     */
    protected int $hits = 0;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->hits = 0;
    }

    /**
     * Register a JSON route that counts how often its handler runs.
     *
     * @param  string  $middleware  The full middleware string, e.g. `rache:auth`.
     * @param  string  $name  The route name, or an empty string for an unnamed route.
     * @param  string  $uri  The route URI.
     */
    protected function countingRoute(string $middleware = 'rache', string $name = 'posts.index', string $uri = '/posts'): void
    {
        $route = Route::get($uri, function () {
            $this->hits++;

            return response()->json(['hits' => $this->hits]);
        })->middleware($middleware);

        if ($name !== '') {
            $route->name($name);
        }
    }

    #[Test]
    public function it_serves_the_second_request_from_the_cache(): void
    {
        $this->countingRoute('rache:auth,page');

        $this->getJson('/posts')->assertOk()->assertJson(['hits' => 1]);
        $this->getJson('/posts')->assertOk()->assertJson(['hits' => 1]);

        $this->assertSame(1, $this->hits);
    }

    #[Test]
    public function it_caches_a_route_that_uses_no_tags(): void
    {
        $this->countingRoute('rache');

        $this->getJson('/posts')->assertJson(['hits' => 1]);
        $this->getJson('/posts')->assertJson(['hits' => 1]);

        $this->assertSame(1, $this->hits);
    }

    #[Test]
    public function it_varies_the_cache_by_tag_data(): void
    {
        $this->countingRoute('rache:page');

        $this->getJson('/posts?page=1')->assertJson(['hits' => 1]);
        $this->getJson('/posts?page=2')->assertJson(['hits' => 2]);
        $this->getJson('/posts?page=1')->assertJson(['hits' => 1]);

        $this->assertSame(2, $this->hits);
    }

    #[Test]
    public function it_ignores_query_input_that_no_tag_covers(): void
    {
        $this->countingRoute('rache');

        $this->getJson('/posts?page=1')->assertJson(['hits' => 1]);
        $this->getJson('/posts?page=2')->assertJson(['hits' => 1]);

        $this->assertSame(1, $this->hits);
    }

    #[Test]
    public function the_request_tag_varies_the_cache_by_every_input(): void
    {
        $this->countingRoute('rache:request');

        $this->getJson('/posts?q=a')->assertJson(['hits' => 1]);
        $this->getJson('/posts?q=b')->assertJson(['hits' => 2]);
        $this->getJson('/posts?q=a')->assertJson(['hits' => 1]);

        $this->assertSame(2, $this->hits);
    }

    #[Test]
    public function it_accepts_the_ttl_parameter_in_any_position(): void
    {
        Route::get('/a', fn () => response()->json([]))->middleware('rache:ttl_10,auth,page')->name('a');
        Route::get('/b', fn () => response()->json([]))->middleware('rache:auth,ttl_10,page')->name('b');

        $this->getJson('/a')->assertOk();
        $this->assertSame(10, Rache::getLifetime());

        $this->getJson('/b')->assertOk();
        $this->assertSame(10, Rache::getLifetime());
    }

    #[Test]
    public function it_tolerates_whitespace_around_middleware_parameters(): void
    {
        // Regression: `rache:auth, page` (a space after the comma, which reads
        // perfectly naturally in a route file) used to report the registered
        // `page` tag as unregistered.
        $this->countingRoute('rache:auth, page ,auth');

        $this->getJson('/posts')->assertOk()->assertJson(['hits' => 1]);
        $this->getJson('/posts')->assertJson(['hits' => 1]);
    }

    #[Test]
    public function it_does_not_cache_non_idempotent_requests(): void
    {
        // Regression: there was no method check at all, so a rache'd POST route
        // replayed its first response to every later caller.
        Route::post('/posts', function () {
            $this->hits++;

            return response()->json(['hits' => $this->hits]);
        })->middleware('rache')->name('posts.store');

        $this->postJson('/posts')->assertJson(['hits' => 1]);
        $this->postJson('/posts')->assertJson(['hits' => 2]);

        $this->assertSame(2, $this->hits);
    }

    #[Test]
    public function it_caches_a_method_that_has_been_opted_in_through_config(): void
    {
        config()->set('rache.cacheable_methods', ['get', 'post']);

        Route::post('/posts', function () {
            $this->hits++;

            return response()->json(['hits' => $this->hits]);
        })->middleware('rache')->name('posts.store');

        $this->postJson('/posts')->assertJson(['hits' => 1]);
        $this->postJson('/posts')->assertJson(['hits' => 1]);
    }

    #[Test]
    public function it_does_nothing_when_rache_is_disabled(): void
    {
        config()->set('rache.enabled', false);
        $this->countingRoute();

        $this->getJson('/posts')->assertJson(['hits' => 1]);
        $this->getJson('/posts')->assertJson(['hits' => 2]);

        $this->assertFalse(Rache::isInitialized());
    }

    #[Test]
    public function it_honours_a_string_false_from_the_environment(): void
    {
        config()->set('rache.enabled', 'false');
        $this->countingRoute();

        $this->getJson('/posts')->assertJson(['hits' => 1]);
        $this->getJson('/posts')->assertJson(['hits' => 2]);
    }

    #[Test]
    public function it_requires_a_named_route(): void
    {
        $this->withoutExceptionHandling();
        $this->countingRoute('rache', '');

        $this->expectException(MissingRouteNameException::class);
        $this->expectExceptionMessage('requires a named route');

        $this->getJson('/posts');
    }

    #[Test]
    public function it_does_not_cache_failed_responses(): void
    {
        Route::get('/boom', function () {
            $this->hits++;

            return response()->json(['error' => true], 500);
        })->middleware('rache')->name('boom');

        $this->getJson('/boom')->assertStatus(500);
        $this->getJson('/boom')->assertStatus(500);

        $this->assertSame(2, $this->hits);
    }

    #[Test]
    public function it_caches_redirects(): void
    {
        // Regression: Illuminate\Http\RedirectResponse holds the request and
        // the session, which hold closures, so writing one to any serializing
        // store (redis, memcached, file) threw "Serialization of 'Closure' is
        // not allowed" and turned the redirect into a 500.
        Route::get('/go', function () {
            $this->hits++;

            return redirect('/elsewhere');
        })->middleware('rache')->name('go');

        $this->get('/go')->assertRedirect('/elsewhere');
        $this->get('/go')->assertRedirect('/elsewhere');

        $this->assertSame(1, $this->hits);
    }

    #[Test]
    public function it_caches_an_upper_cased_content_type(): void
    {
        // Regression: media types are case insensitive per RFC 9110, but the
        // match was case sensitive, so `Application/JSON` was silently never
        // cached and the middleware just quietly did nothing.
        Route::get('/shouty', function () {
            $this->hits++;

            return new Response('{"ok":true}', 200, ['Content-Type' => 'Application/JSON; CHARSET=UTF-8']);
        })->middleware('rache')->name('shouty');

        $this->get('/shouty')->assertOk();
        $this->get('/shouty')->assertOk();

        $this->assertSame(1, $this->hits);
    }

    #[Test]
    public function it_skips_responses_with_an_uncacheable_content_type(): void
    {
        Route::get('/binary', function () {
            $this->hits++;

            return new Response('bytes', 200, ['Content-Type' => 'application/octet-stream']);
        })->middleware('rache')->name('binary');

        $this->get('/binary')->assertOk();
        $this->get('/binary')->assertOk();

        $this->assertSame(2, $this->hits);
    }

    #[Test]
    public function it_skips_streamed_responses(): void
    {
        // Regression: shouldCacheResponse() type-hinted Illuminate\Http\Response,
        // so returning a stream from a rache'd route raised a TypeError.
        Route::get('/stream', function () {
            $this->hits++;

            return new StreamedResponse(function () {
                echo 'chunk';
            }, 200, ['Content-Type' => 'text/plain']);
        })->middleware('rache')->name('stream');

        $this->get('/stream')->assertOk();
        $this->get('/stream')->assertOk();

        $this->assertSame(2, $this->hits);
    }

    #[Test]
    public function it_strips_set_cookie_headers_from_the_cached_copy(): void
    {
        // Regression: a controller-set cookie was serialized into the shared
        // cache entry and replayed to every later visitor.
        Route::get('/cookie', function () {
            $this->hits++;

            return response()->json(['hits' => $this->hits])->cookie('visitor', 'first', 60);
        })->middleware('rache')->name('cookie');

        $first = $this->get('/cookie');
        $first->assertOk();
        $this->assertNotEmpty($first->headers->getCookies(), 'the originating visitor still receives their cookie');

        $second = $this->get('/cookie');
        $second->assertOk();
        $this->assertSame([], $second->headers->getCookies(), 'the cached copy must not carry another visitor cookie');
    }

    #[Test]
    public function it_can_keep_set_cookie_headers_when_configured_to(): void
    {
        config()->set('rache.strip_set_cookie_header', false);

        Route::get('/cookie', fn () => response()->json([])->cookie('visitor', 'first', 60))
            ->middleware('rache')->name('cookie');

        $this->get('/cookie')->assertOk();
        $this->assertNotEmpty($this->get('/cookie')->headers->getCookies());
    }

    #[Test]
    public function it_separates_cache_entries_per_request_in_a_long_lived_worker(): void
    {
        // Regression: initialize() returned early once the singleton had been
        // initialized, so under Octane the second request reused the first
        // request's cache key and could be served another visitor's response.
        $this->countingRoute('rache:page', 'posts.index');

        $this->getJson('/posts?page=1');
        $first = Rache::getCacheKey();

        $this->getJson('/posts?page=2');
        $second = Rache::getCacheKey();

        $this->assertNotSame($first, $second);
    }
}
