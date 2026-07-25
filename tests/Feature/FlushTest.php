<?php

declare(strict_types=1);

namespace Rache\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Rache\Exceptions\TagNotRegisteredException;
use Rache\Facades\Rache;
use Rache\Tests\TestCase;

/**
 * Covers Rache::flushAll() and Rache::flushTag().
 */
class FlushTest extends TestCase
{
    /**
     * The number of times each route handler has run, keyed by route name.
     *
     * @var array<string, int>
     */
    protected array $hits = [];

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->hits = [];
    }

    /**
     * Register a counting JSON route.
     *
     * @param  string  $uri  The route URI.
     * @param  string  $name  The route name.
     * @param  string  $middleware  The full middleware string.
     */
    protected function countingRoute(string $uri, string $name, string $middleware): void
    {
        Route::get($uri, function () use ($name) {
            $this->hits[$name] = ($this->hits[$name] ?? 0) + 1;

            return response()->json(['hits' => $this->hits[$name]]);
        })->middleware($middleware)->name($name);
    }

    #[Test]
    public function flush_all_clears_a_tagged_response(): void
    {
        $this->countingRoute('/posts', 'posts.index', 'rache:auth');

        $this->getJson('/posts');
        $this->getJson('/posts');
        $this->assertSame(1, $this->hits['posts.index']);

        Rache::flushAll();

        $this->getJson('/posts');
        $this->assertSame(2, $this->hits['posts.index']);
    }

    #[Test]
    public function flush_all_clears_a_response_cached_without_any_tags(): void
    {
        // Regression: the global `rache` tag was only added inside the per-tag
        // loop, so a route documented as `->middleware('rache')` was invisible
        // to flushAll() and stayed stale until its TTL expired.
        $this->countingRoute('/bare', 'bare.index', 'rache');

        $this->getJson('/bare');
        $this->getJson('/bare');
        $this->assertSame(1, $this->hits['bare.index']);

        Rache::flushAll();

        $this->getJson('/bare');
        $this->assertSame(2, $this->hits['bare.index'], 'flushAll() must clear untagged responses too');
    }

    #[Test]
    public function flushing_a_tag_clears_every_route_carrying_it(): void
    {
        $this->countingRoute('/posts', 'posts.index', 'rache:auth');
        $this->countingRoute('/pages', 'pages.index', 'rache:auth');

        $this->getJson('/posts');
        $this->getJson('/pages');

        Rache::flushTag('auth');

        $this->getJson('/posts');
        $this->getJson('/pages');

        $this->assertSame(2, $this->hits['posts.index']);
        $this->assertSame(2, $this->hits['pages.index']);
    }

    #[Test]
    public function flushing_a_tag_for_one_route_leaves_the_others_alone(): void
    {
        $this->countingRoute('/posts', 'posts.index', 'rache:auth');
        $this->countingRoute('/pages', 'pages.index', 'rache:auth');

        $this->getJson('/posts');
        $this->getJson('/pages');

        Rache::flushTag('auth', ['route' => 'posts.index']);

        $this->getJson('/posts');
        $this->getJson('/pages');

        $this->assertSame(2, $this->hits['posts.index']);
        $this->assertSame(1, $this->hits['pages.index']);
    }

    #[Test]
    public function flushing_a_tag_for_one_data_set_leaves_the_others_alone(): void
    {
        $this->countingRoute('/posts', 'posts.index', 'rache:page');

        $this->getJson('/posts?page=1');
        $this->getJson('/posts?page=2');
        $this->assertSame(2, $this->hits['posts.index']);

        Rache::flushTag('page', [
            'route' => 'posts.index',
            'data' => ['page' => '1'],
        ]);

        $this->getJson('/posts?page=1');
        $this->assertSame(3, $this->hits['posts.index'], 'page 1 should have been evicted');

        $this->getJson('/posts?page=2');
        $this->assertSame(3, $this->hits['posts.index'], 'page 2 should still be cached');
    }

    #[Test]
    public function flushing_a_tag_by_data_matches_regardless_of_key_order(): void
    {
        Route::get('/posts', function () {
            $this->hits['posts.index'] = ($this->hits['posts.index'] ?? 0) + 1;

            return response()->json([]);
        })->middleware('rache:request')->name('posts.index');

        $this->getJson('/posts?a=1&b=2');
        $this->assertSame(1, $this->hits['posts.index']);

        Rache::flushTag('request', [
            'route' => 'posts.index',
            'data' => ['b' => '2', 'a' => '1'],
        ]);

        $this->getJson('/posts?a=1&b=2');
        $this->assertSame(2, $this->hits['posts.index']);
    }

    #[Test]
    public function flushing_an_unregistered_tag_throws(): void
    {
        $this->expectException(TagNotRegisteredException::class);
        $this->expectExceptionMessage('Tag name is not declared in the rache config for nope');

        Rache::flushTag('nope');
    }

    #[Test]
    public function the_exception_lists_the_tags_that_are_registered(): void
    {
        $this->expectExceptionMessage('Registered tags: auth, page, request');

        Rache::flushTag('nope');
    }

    #[Test]
    public function flush_all_works_before_any_request_has_been_cached(): void
    {
        $this->assertTrue(Rache::flushAll());
    }
}
